<?php

declare(strict_types=1);

namespace AvS\ScopeHint\Service;

use Magento\Eav\Model\Config as EavConfig;
use Magento\Eav\Model\Entity\Attribute\AbstractAttribute;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\EntityManager\MetadataPool;
use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Bulk EAV override fetcher for the product grid.
 *
 * For each EAV backend type table, executes a self-join against the admin (store_id=0)
 * row to find per-store values that differ from the default scope. Returns a nested
 * array indexed by productId → attributeCode → storeId → override data.
 *
 * Only non-global attributes with supported frontend_input types are included.
 * Image/media/gallery attributes and SCOPE_GLOBAL attributes are silently skipped.
 */
class ProductOverrideAggregator
{
    /**
     * EAV backend type tables to scan.
     *
     * @var string[]
     */
    private static $backendTypes = ['varchar', 'int', 'decimal', 'text', 'datetime'];

    /**
     * frontend_input values to skip entirely.
     *
     * @var string[]
     */
    private static $skippedInputTypes = ['image', 'media_image', 'gallery'];

    /** @var ResourceConnection */
    private $resource;

    /** @var EavConfig */
    private $eavConfig;

    /** @var StoreManagerInterface */
    private $storeManager;

    /** @var MetadataPool */
    private $metadataPool;

    /**
     * Per-request cache keyed by sorted product-id CSV.
     *
     * @var array<string, array<int, array<string, array<int, array<string, string>>>>>
     */
    private $cache = [];

    /**
     * Cached store list (populated once per request).
     *
     * @var array<int, array<string, string>>|null
     */
    private $storeMap = null;

    public function __construct(
        ResourceConnection $resource,
        EavConfig $eavConfig,
        StoreManagerInterface $storeManager,
        MetadataPool $metadataPool
    ) {
        $this->resource     = $resource;
        $this->eavConfig    = $eavConfig;
        $this->storeManager = $storeManager;
        $this->metadataPool = $metadataPool;
    }

    /**
     * Returns per-(product, attributeCode, storeId) override rows for a batch of products.
     *
     * Structure:
     *   [productId => [attributeCode => [storeId => [displayValue, storeName, storeCode, websiteCode]]]]
     *
     * @param int[] $productIds
     * @return array<int, array<string, array<int, array<string, string>>>>
     */
    public function getOverrides(array $productIds): array
    {
        if (empty($productIds)) {
            return [];
        }

        sort($productIds);
        $cacheKey = implode(',', $productIds);

        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $result     = [];
        $connection = $this->resource->getConnection();
        $storeMap   = $this->getStoreMap();
        $metadata = $this->metadataPool->getMetadata(ProductInterface::class);
        $linkField = $metadata->getLinkField();
        $identifierField = $metadata->getIdentifierField();
        $entityTable = $this->resource->getTableName('catalog_product_entity');

        foreach (self::$backendTypes as $backendType) {
            $tableName = $this->resource->getTableName('catalog_product_entity_' . $backendType);

            // Self-join: t = store-scoped row, admin = admin (store_id=0) row for same product+attribute.
            // NULL-safe inequality NOT (t.value <=> admin.value) ensures we exclude rows that merely
            // copy the admin value even when both are NULL.
            $select = $connection->select()
                ->from(
                    ['t' => $tableName],
                    ['attribute_id', 'store_id', 'value']
                )
                ->joinInner(
                    ['entity' => $entityTable],
                    'entity.' . $linkField . ' = t.' . $linkField,
                    ['entity_id' => $identifierField]
                )
                ->joinInner(
                    ['admin' => $tableName],
                    'admin.' . $linkField . ' = t.' . $linkField
                    . ' AND admin.attribute_id = t.attribute_id'
                    . ' AND admin.store_id = 0',
                    []
                )
                ->where('entity.' . $identifierField . ' IN (?)', $productIds)
                ->where('t.store_id != ?', 0)
                ->where('NOT (t.value <=> admin.value)');

            $rows = $connection->fetchAll($select);

            foreach ($rows as $row) {
                $attributeId = (int) $row['attribute_id'];
                $entityId    = (int) $row['entity_id'];
                $storeId     = (int) $row['store_id'];
                $value       = $row['value'];

                $attribute = $this->resolveAttribute($attributeId);

                if ($attribute === null) {
                    continue;
                }

                $attributeCode = $attribute->getAttributeCode();

                if (!isset($storeMap[$storeId])) {
                    continue;
                }

                $storeInfo    = $storeMap[$storeId];
                $displayValue = $this->formatValue($value, $attribute);

                $result[$entityId][$attributeCode][$storeId] = [
                    'displayValue' => $displayValue,
                    'storeName'    => $storeInfo['storeName'],
                    'storeCode'    => $storeInfo['storeCode'],
                    'websiteCode'  => $storeInfo['websiteCode'],
                ];
            }
        }

        $this->cache[$cacheKey] = $result;

        return $result;
    }

    /**
     * Resolve an attribute by ID, filtering out skipped types and global-scope attributes.
     * Returns null when the attribute should be ignored.
     */
    private function resolveAttribute(int $attributeId): ?AbstractAttribute
    {
        try {
            $attribute = $this->eavConfig->getAttribute('catalog_product', $attributeId);

            if (!$attribute || !$attribute->getAttributeId()) {
                return null;
            }

            // Skip global-scope attributes — they have no per-store storage.
            // getIsGlobal() lives on Magento\Catalog\Model\ResourceModel\Eav\Attribute;
            // fall back to getData() which works on all DataObject-based attributes.
            $isGlobal = method_exists($attribute, 'getIsGlobal')
                ? (int) $attribute->getIsGlobal()
                : (int) $attribute->getData('is_global');

            if ($isGlobal === \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL) {
                return null;
            }

            // Skip image / media / gallery attributes.
            if (in_array($attribute->getFrontendInput(), self::$skippedInputTypes, true)) {
                return null;
            }

            return $attribute;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Format a raw EAV value to a display string.
     * Datetime values are cast to string; all others are cast directly.
     */
    private function formatValue($value, AbstractAttribute $attribute): string
    {
        if ($value === null) {
            return '';
        }

        return (string) $value;
    }

    /**
     * Returns a map of storeId → [storeName, storeCode, websiteCode].
     *
     * @return array<int, array<string, string>>
     */
    private function getStoreMap(): array
    {
        if ($this->storeMap !== null) {
            return $this->storeMap;
        }

        $this->storeMap = [];

        try {
            $stores = $this->storeManager->getStores(false);

            foreach ($stores as $store) {
                $storeId     = (int) $store->getId();
                $websiteCode = '';

                try {
                    $websiteCode = (string) $this->storeManager->getWebsite($store->getWebsiteId())->getCode();
                } catch (\Throwable $e) {
                    // fall through
                }

                $this->storeMap[$storeId] = [
                    'storeName'   => (string) $store->getName(),
                    'storeCode'   => (string) $store->getCode(),
                    'websiteCode' => $websiteCode,
                ];
            }
        } catch (\Throwable $e) {
            // Return empty map if store manager is unavailable.
        }

        return $this->storeMap;
    }
}
