<?php

declare(strict_types=1);

namespace AvS\ScopeHint\Test\Unit\Service;

use AvS\ScopeHint\Service\ProductOverrideAggregator;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ProductOverrideAggregatorTest extends TestCase
{
    /** @var ResourceConnection&MockObject */
    private $resource;

    /** @var ProductOverrideAggregator */
    private $aggregator;

    protected function setUp(): void
    {
        $this->resource = $this->createMock(ResourceConnection::class);

        $this->aggregator = new ProductOverrideAggregator(
            $this->resource
        );
    }

    public function testEmptyInputReturnsEmptyArray(): void
    {
        $this->resource->expects($this->never())->method('getConnection');

        $result = $this->aggregator->getOverrideCounts([]);

        $this->assertSame([], $result);
    }

    public function testSingleBackendTypeRowAccumulatesCorrectly(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $this->resource->method('getConnection')->willReturn($connection);
        $this->resource->method('getTableName')->willReturnCallback(
            static function (string $table) {
                return $table;
            }
        );

        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('join')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        $select->method('group')->willReturnSelf();

        $connection->method('select')->willReturn($select);

        // varchar returns one row, all others empty
        $connection->method('fetchAll')->willReturnOnConsecutiveCalls(
            [['entity_id' => '1', 'store_id' => '1', 'field_count' => '2']],
            [],
            [],
            [],
            []
        );

        $result = $this->aggregator->getOverrideCounts([1]);

        $this->assertSame([1 => [1 => 2]], $result);
    }

    public function testMultipleBackendTypesAccumulateSumForSamePair(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $this->resource->method('getConnection')->willReturn($connection);
        $this->resource->method('getTableName')->willReturnCallback(
            static function (string $table) {
                return $table;
            }
        );

        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('join')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        $select->method('group')->willReturnSelf();

        $connection->method('select')->willReturn($select);

        // varchar: entity 1 store 1 = 2 fields, int: entity 1 store 1 = 1 field → sum = 3
        $connection->method('fetchAll')->willReturnOnConsecutiveCalls(
            [['entity_id' => '1', 'store_id' => '1', 'field_count' => '2']],
            [['entity_id' => '1', 'store_id' => '1', 'field_count' => '1']],
            [],
            [],
            []
        );

        $result = $this->aggregator->getOverrideCounts([1]);

        $this->assertSame(3, $result[1][1], 'varchar(2) + int(1) should sum to 3');
    }

    public function testCacheHitMeansConnectionAccessedOnce(): void
    {
        $connection = $this->createMock(AdapterInterface::class);

        // getConnection is called once per getOverrideCounts invocation (connection is a local variable).
        // The second call should hit the cache and never touch the connection at all.
        $this->resource->expects($this->once())
            ->method('getConnection')
            ->willReturn($connection);

        $this->resource->method('getTableName')->willReturnCallback(
            static function (string $table) {
                return $table;
            }
        );

        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('join')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        $select->method('group')->willReturnSelf();

        $connection->method('select')->willReturn($select);
        $connection->method('fetchAll')->willReturn([]);

        $this->aggregator->getOverrideCounts([42]);
        $this->aggregator->getOverrideCounts([42]);

        // Assertion is the expects($this->exactly(5)) above — no extra assertions needed
        $this->addToAssertionCount(1);
    }

    public function testMultipleStoresAndProductsPopulateNestedMap(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $this->resource->method('getConnection')->willReturn($connection);
        $this->resource->method('getTableName')->willReturnCallback(
            static function (string $table) {
                return $table;
            }
        );

        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('join')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        $select->method('group')->willReturnSelf();

        $connection->method('select')->willReturn($select);

        // varchar: product 10 store 1 = 3, product 10 store 2 = 1, product 20 store 1 = 2
        $connection->method('fetchAll')->willReturnOnConsecutiveCalls(
            [
                ['entity_id' => '10', 'store_id' => '1', 'field_count' => '3'],
                ['entity_id' => '10', 'store_id' => '2', 'field_count' => '1'],
                ['entity_id' => '20', 'store_id' => '1', 'field_count' => '2'],
            ],
            [],
            [],
            [],
            []
        );

        $result = $this->aggregator->getOverrideCounts([10, 20]);

        $this->assertSame(3, $result[10][1]);
        $this->assertSame(1, $result[10][2]);
        $this->assertSame(2, $result[20][1]);
        $this->assertArrayNotHasKey(2, $result[20] ?? []);
    }
}
