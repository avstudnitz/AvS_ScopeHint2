<?php

declare(strict_types=1);

namespace AvS\ScopeHint\Service;

use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\App\ResourceConnection;

class ProductOverrideAggregator
{
    /** @var ResourceConnection */
    private $resource;

    /** @var EavConfig */
    private $eavConfig;

    /** @var array<string, array<int, int>> */
    private $cache = [];

    /** @var string[] */
    private $attributeCodes = ['name', 'status', 'visibility', 'price', 'special_price'];

    /** @var string[] */
    private $backendTypes = ['varchar', 'int', 'decimal', 'text', 'datetime'];

    public function __construct(
        ResourceConnection $resource,
        EavConfig $eavConfig
    ) {
        $this->resource = $resource;
        $this->eavConfig = $eavConfig;
    }

    /**
     * Returns a map of productId => distinct-store-override-count.
     * Only stores where store_id != 0 (non-default scope) are counted.
     *
     * @param int[] $productIds
     * @return array<int, int>
     */
    public function getOverrideCounts(array $productIds): array
    {
        if (empty($productIds)) {
            return [];
        }

        sort($productIds);
        $cacheKey = implode(',', $productIds);

        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $attributeIds = $this->resolveAttributeIds();

        if (empty($attributeIds)) {
            $this->cache[$cacheKey] = [];
            return [];
        }

        $counts = array_fill_keys($productIds, 0);
        $connection = $this->resource->getConnection();

        foreach ($this->backendTypes as $backendType) {
            $tableName = $this->resource->getTableName('catalog_product_entity_' . $backendType);

            $select = $connection->select()
                ->from($tableName, ['entity_id', 'store_id'])
                ->where('entity_id IN (?)', $productIds)
                ->where('attribute_id IN (?)', $attributeIds)
                ->where('store_id != ?', 0)
                ->distinct(true);

            $rows = $connection->fetchAll($select);

            // Accumulate distinct (entity_id, store_id) pairs across all backend type tables.
            // We track unique pairs in $seen to avoid double-counting the same store
            // for different attributes within the same backend type table on one pass.
            // The per-entity distinct store set is built across all five table queries below.
            foreach ($rows as $row) {
                $entityId = (int) $row['entity_id'];
                $storeId = (int) $row['store_id'];

                if (!isset($counts[$entityId])) {
                    continue;
                }

                // Use a nested tracker to accumulate distinct stores per product across tables
                if (!isset($seen[$entityId])) {
                    $seen[$entityId] = [];
                }
                $seen[$entityId][$storeId] = true;
            }
        }

        // Convert seen store sets to counts
        if (isset($seen)) {
            foreach ($seen as $entityId => $stores) {
                $counts[$entityId] = count($stores);
            }
        }

        $this->cache[$cacheKey] = $counts;

        return $counts;
    }

    /**
     * @return int[]
     */
    private function resolveAttributeIds(): array
    {
        $ids = [];

        foreach ($this->attributeCodes as $code) {
            try {
                $attribute = $this->eavConfig->getAttribute('catalog_product', $code);

                if ($attribute && $attribute->getAttributeId()) {
                    $ids[] = (int) $attribute->getAttributeId();
                }
            } catch (\Throwable $e) {
                // Skip attributes that don't exist in this installation
            }
        }

        return array_unique($ids);
    }
}
