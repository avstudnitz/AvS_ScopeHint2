<?php

declare(strict_types=1);

namespace AvS\ScopeHint\Service;

use Magento\Framework\App\ResourceConnection;

class ProductOverrideAggregator
{
    /** @var ResourceConnection */
    private $resource;

    /** @var array<string, array<int, array<int, int>>> */
    private $cache = [];

    /** @var string[] */
    private $backendTypes = ['varchar', 'int', 'decimal', 'text', 'datetime'];

    public function __construct(
        ResourceConnection $resource
    ) {
        $this->resource = $resource;
    }

    /**
     * Returns a nested map of productId => storeId => fieldCount.
     * Only counts fields where the store-scope value differs from the admin-scope value.
     *
     * @param int[] $productIds
     * @return array<int, array<int, int>>
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

        /** @var array<int, array<int, int>> $counts */
        $counts = [];
        $connection = $this->resource->getConnection();

        foreach ($this->backendTypes as $backendType) {
            $tableName = $this->resource->getTableName('catalog_product_entity_' . $backendType);

            $select = $connection->select()
                ->from(
                    ['t' => $tableName],
                    ['entity_id', 'store_id', 'field_count' => 'COUNT(DISTINCT t.attribute_id)']
                )
                ->join(
                    ['admin' => $tableName],
                    'admin.entity_id = t.entity_id'
                    . ' AND admin.attribute_id = t.attribute_id'
                    . ' AND admin.store_id = 0',
                    []
                )
                ->where('t.entity_id IN (?)', $productIds)
                ->where('t.store_id != ?', 0)
                ->where('NOT (t.value <=> admin.value)')
                ->group(['t.entity_id', 't.store_id']);

            $rows = $connection->fetchAll($select);

            foreach ($rows as $row) {
                $entityId = (int) $row['entity_id'];
                $storeId = (int) $row['store_id'];
                $fieldCount = (int) $row['field_count'];

                if (!isset($counts[$entityId])) {
                    $counts[$entityId] = [];
                }

                $counts[$entityId][$storeId] = ($counts[$entityId][$storeId] ?? 0) + $fieldCount;
            }
        }

        $this->cache[$cacheKey] = $counts;

        return $counts;
    }
}
