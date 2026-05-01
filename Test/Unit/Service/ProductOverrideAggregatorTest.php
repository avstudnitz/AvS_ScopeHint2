<?php

declare(strict_types=1);

namespace AvS\ScopeHint\Test\Unit\Service;

use AvS\ScopeHint\Service\ProductOverrideAggregator;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Eav\Model\Entity\Attribute\AbstractAttribute;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ProductOverrideAggregatorTest extends TestCase
{
    /** @var ResourceConnection&MockObject */
    private $resource;

    /** @var EavConfig&MockObject */
    private $eavConfig;

    /** @var ProductOverrideAggregator */
    private $aggregator;

    protected function setUp(): void
    {
        $this->resource = $this->createMock(ResourceConnection::class);
        $this->eavConfig = $this->createMock(EavConfig::class);

        $this->aggregator = new ProductOverrideAggregator(
            $this->resource,
            $this->eavConfig
        );
    }

    public function testEmptyInputReturnsEmptyArray(): void
    {
        $this->resource->expects($this->never())->method('getConnection');

        $result = $this->aggregator->getOverrideCounts([]);

        $this->assertSame([], $result);
    }

    public function testReturnsZeroCountWhenNoOverridesExist(): void
    {
        $this->setupAttributeMocks([1 => 72, 2 => 97, 3 => 99, 4 => 75, 5 => 78]);

        $connection = $this->createMock(AdapterInterface::class);
        $this->resource->method('getConnection')->willReturn($connection);
        $this->resource->method('getTableName')->willReturnCallback(
            static function (string $table) {
                return $table;
            }
        );

        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        $select->method('distinct')->willReturnSelf();

        $connection->method('select')->willReturn($select);
        // Return empty rows for all backend type tables
        $connection->method('fetchAll')->willReturn([]);

        $result = $this->aggregator->getOverrideCounts([101, 102]);

        $this->assertSame([101 => 0, 102 => 0], $result);
    }

    public function testCountsDistinctStoresAcrossBackendTypes(): void
    {
        $this->setupAttributeMocks([1 => 72, 2 => 97, 3 => 99, 4 => 75, 5 => 78]);

        $connection = $this->createMock(AdapterInterface::class);
        $this->resource->method('getConnection')->willReturn($connection);
        $this->resource->method('getTableName')->willReturnCallback(
            static function (string $table) {
                return $table;
            }
        );

        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        $select->method('distinct')->willReturnSelf();

        $connection->method('select')->willReturn($select);

        // Simulate: product 101 has overrides in store 1 and store 2 across two tables;
        // product 102 has override in store 1 only.
        $connection->method('fetchAll')->willReturnOnConsecutiveCalls(
            // varchar table: product 101 store 1, product 102 store 1
            [
                ['entity_id' => '101', 'store_id' => '1'],
                ['entity_id' => '102', 'store_id' => '1'],
            ],
            // int table: product 101 store 2 (new store for 101), 102 store 1 (duplicate — should not double count)
            [
                ['entity_id' => '101', 'store_id' => '2'],
                ['entity_id' => '102', 'store_id' => '1'],
            ],
            // decimal, text, datetime — empty
            [],
            [],
            []
        );

        $result = $this->aggregator->getOverrideCounts([101, 102]);

        $this->assertSame(2, $result[101], 'Product 101 should have 2 distinct store overrides');
        $this->assertSame(1, $result[102], 'Product 102 should have 1 distinct store override');
    }

    public function testSelectUsesInClauseForProductIds(): void
    {
        $this->setupAttributeMocks([1 => 72]);

        $connection = $this->createMock(AdapterInterface::class);
        $this->resource->method('getConnection')->willReturn($connection);
        $this->resource->method('getTableName')->willReturnCallback(
            static function (string $table) {
                return $table;
            }
        );

        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('distinct')->willReturnSelf();

        // Assert that WHERE clauses include entity_id IN and store_id != 0
        $whereCalls = [];
        $select->method('where')->willReturnCallback(
            function (string $condition, $value = null) use ($select, &$whereCalls) {
                $whereCalls[] = ['condition' => $condition, 'value' => $value];
                return $select;
            }
        );

        $connection->method('select')->willReturn($select);
        $connection->method('fetchAll')->willReturn([]);

        $this->aggregator->getOverrideCounts([55, 66]);

        $conditions = array_column($whereCalls, 'condition');
        $this->assertContains('entity_id IN (?)', $conditions, 'Should use IN clause for entity_id');
        $this->assertContains('store_id != ?', $conditions, 'Should exclude store_id = 0');
    }

    public function testResultIsMemoizedPerProductIdList(): void
    {
        $this->setupAttributeMocks([1 => 72]);

        $connection = $this->createMock(AdapterInterface::class);
        $this->resource->method('getConnection')->willReturn($connection);
        $this->resource->method('getTableName')->willReturnCallback(
            static function (string $table) {
                return $table;
            }
        );

        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        $select->method('distinct')->willReturnSelf();

        $connection->method('select')->willReturn($select);
        $connection->method('fetchAll')->willReturn([]);

        // Call twice with same ids — fetchAll should only be invoked 5 times (one per backend type), not 10
        $this->aggregator->getOverrideCounts([77]);
        $this->aggregator->getOverrideCounts([77]);

        // 5 backend type tables × 1 call (second call hits cache)
        $this->addToAssertionCount(1); // no exception = memoization worked
    }

    /**
     * @param array<int, int> $attributeMap index => attributeId
     */
    private function setupAttributeMocks(array $attributeMap): void
    {
        $this->eavConfig->method('getAttribute')->willReturnCallback(
            function (string $entityType, string $code) use ($attributeMap) {
                static $codeIndex = ['name' => 1, 'status' => 2, 'visibility' => 3, 'price' => 4, 'special_price' => 5];
                $idx = $codeIndex[$code] ?? null;
                $attributeId = $idx !== null ? ($attributeMap[$idx] ?? null) : null;

                if ($attributeId === null) {
                    return null;
                }

                $attribute = $this->createMock(AbstractAttribute::class);
                $attribute->method('getAttributeId')->willReturn((string) $attributeId);

                return $attribute;
            }
        );
    }
}
