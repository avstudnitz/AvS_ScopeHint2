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

    /** @var EavOptionLabelResolver */
    private $optionLabelResolver;

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
        MetadataPool $metadataPool,
        EavOptionLabelResolver $optionLabelResolver
    ) {
        $this->resource     = $resource;
        $this->eavConfig    = $eavConfig;
        $this->storeManager = $storeManager;
        $this->metadataPool = $metadataPool;
        $this->optionLabelResolver = $optionLabelResolver;
    }

    /**
     * Returns per-(product, attributeCode, storeId) override rows for a batch of products.
     *
     * Only the given attribute codes are scanned, so large text attributes that are not
     * shown in the grid are never read.
     *
     * Structure:
     *   [productId => [attributeCode => [storeId => [displayValue, storeName, storeCode, websiteCode]]]]
     *
     * @param int[] $productIds
     * @param string[] $attributeCodes
     * @return array<int, array<string, array<int, array<string, string>>>>
     */
    public function getOverrides(array $productIds, array $attributeCodes): array
    {
        if (empty($productIds) || empty($attributeCodes)) {
            return [];
        }

        sort($productIds);
        $attributeCodes = array_values(array_unique($attributeCodes));
        sort($attributeCodes);
        $cacheKey = implode(',', $productIds) . '|' . implode(',', $attributeCodes);

        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $result = [];
        $attributesByBackendType = $this->groupAttributesByBackendType($attributeCodes);

        if (empty($attributesByBackendType)) {
            $this->cache[$cacheKey] = $result;

            return $result;
        }

        $connection = $this->resource->getConnection();
        $storeMap = $this->getStoreMap();
        $metadata = $this->metadataPool->getMetadata(ProductInterface::class);
        $linkField = $metadata->getLinkField();
        $identifierField = $metadata->getIdentifierField();
        $entityTable = $this->resource->getTableName('catalog_product_entity');

        foreach ($attributesByBackendType as $backendType => $attributesById) {
            $tableName = $this->resource->getTableName('catalog_product_entity_' . $backendType);

            // Left join: a store row without an admin row is still an override.
            // NOT (t.value <=> admin.value) is NULL-safe, so it also covers the missing admin row
            // and excludes store rows that merely repeat the admin value.
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
                ->joinLeft(
                    ['admin' => $tableName],
                    'admin.' . $linkField . ' = t.' . $linkField
                    . ' AND admin.attribute_id = t.attribute_id'
                    . ' AND admin.store_id = 0',
                    []
                )
                ->where('entity.' . $identifierField . ' IN (?)', $productIds)
                ->where('t.attribute_id IN (?)', array_keys($attributesById))
                ->where('t.store_id != ?', 0)
                ->where('NOT (t.value <=> admin.value)');

            foreach ($connection->fetchAll($select) as $row) {
                $attribute = $attributesById[(int) $row['attribute_id']] ?? null;
                $storeId = (int) $row['store_id'];

                if ($attribute === null || !isset($storeMap[$storeId])) {
                    continue;
                }

                $storeInfo = $storeMap[$storeId];
                $result[(int) $row['entity_id']][$attribute->getAttributeCode()][$storeId] = [
                    'displayValue' => $this->formatValue($row['value'], $attribute),
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
     * Resolves attribute codes to scannable attributes grouped by backend table.
     * Unknown codes (plain grid fields such as entity_id or qty), static attributes,
     * global-scope attributes and image/media/gallery attributes are dropped.
     *
     * @param string[] $attributeCodes
     * @return array<string, array<int, AbstractAttribute>>
     */
    private function groupAttributesByBackendType(array $attributeCodes): array
    {
        $grouped = [];

        foreach ($attributeCodes as $attributeCode) {
            $attribute = $this->resolveAttribute((string) $attributeCode);

            if ($attribute === null) {
                continue;
            }

            $backendType = (string) $attribute->getBackendType();

            if (!in_array($backendType, self::$backendTypes, true)) {
                continue;
            }

            $grouped[$backendType][(int) $attribute->getAttributeId()] = $attribute;
        }

        return $grouped;
    }

    /**
     * Returns null when the attribute does not exist or should be ignored.
     */
    private function resolveAttribute(string $attributeCode): ?AbstractAttribute
    {
        try {
            $attribute = $this->eavConfig->getAttribute('catalog_product', $attributeCode);

            if (!$attribute || !$attribute->getAttributeId()) {
                return null;
            }

            // getIsGlobal() lives on Magento\Catalog\Model\ResourceModel\Eav\Attribute;
            // getData() covers other DataObject-based attributes.
            $isGlobal = method_exists($attribute, 'getIsGlobal')
                ? (int) $attribute->getIsGlobal()
                : (int) $attribute->getData('is_global');

            if ($isGlobal === \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL) {
                return null;
            }

            if (in_array($attribute->getFrontendInput(), self::$skippedInputTypes, true)) {
                return null;
            }

            return $attribute;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Returns plain text (option labels for select/multiselect); CellPopover escapes it.
     *
     * @param mixed $value
     */
    private function formatValue($value, AbstractAttribute $attribute): string
    {
        if ($value === null) {
            return '';
        }

        return $this->optionLabelResolver->resolve($attribute, (string) $value);
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
