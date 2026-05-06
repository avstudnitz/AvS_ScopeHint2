<?php

declare(strict_types=1);

namespace AvS\ScopeHint\Test\Unit\Ui\Component\Listing\Column;

use AvS\ScopeHint\Service\ProductOverrideAggregator;
use AvS\ScopeHint\Ui\Component\Listing\Column\StoreOverrideIndicator;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class StoreOverrideIndicatorTest extends TestCase
{
    /** @var ContextInterface&MockObject */
    private $context;

    /** @var UiComponentFactory&MockObject */
    private $uiComponentFactory;

    /** @var ProductOverrideAggregator&MockObject */
    private $aggregator;

    protected function setUp(): void
    {
        $this->context = $this->createMock(ContextInterface::class);
        $this->uiComponentFactory = $this->createMock(UiComponentFactory::class);
        $this->aggregator = $this->createMock(ProductOverrideAggregator::class);
    }

    private function buildIndicator(array $config = [], string $name = 'scopehint_overrides_5'): StoreOverrideIndicator
    {
        return new StoreOverrideIndicator(
            $this->context,
            $this->uiComponentFactory,
            $this->aggregator,
            [],
            [
                'name'   => $name,
                'config' => array_merge(['storeId' => 5], $config),
            ]
        );
    }

    public function testEmptyItemsReturnsUnchangedDataSource(): void
    {
        $this->aggregator->expects($this->never())->method('getOverrideCounts');

        $dataSource = ['data' => ['items' => []]];
        $indicator = $this->buildIndicator();

        $result = $indicator->prepareDataSource($dataSource);

        $this->assertSame($dataSource, $result);
    }

    public function testMissingItemsKeyReturnsUnchangedDataSource(): void
    {
        $this->aggregator->expects($this->never())->method('getOverrideCounts');

        $dataSource = ['data' => []];
        $indicator = $this->buildIndicator();

        $result = $indicator->prepareDataSource($dataSource);

        $this->assertSame($dataSource, $result);
    }

    public function testItemWithCountGetsBadge(): void
    {
        $this->aggregator->method('getOverrideCounts')
            ->with([1])
            ->willReturn([1 => [5 => 3]]);

        $dataSource = [
            'data' => [
                'items' => [
                    ['entity_id' => '1', 'sku' => 'ABC'],
                ],
            ],
        ];

        $indicator = $this->buildIndicator(['storeId' => 5], 'scopehint_overrides_5');
        $result = $indicator->prepareDataSource($dataSource);

        $cellValue = $result['data']['items'][0]['scopehint_overrides_5'];
        $this->assertStringContainsString('>3<', $cellValue);
        $this->assertStringContainsString('scopehint-overrides-badge', $cellValue);
    }

    public function testItemWithZeroCountGetsEmptyString(): void
    {
        $this->aggregator->method('getOverrideCounts')
            ->willReturn([]);

        $dataSource = [
            'data' => [
                'items' => [
                    ['entity_id' => '2', 'sku' => 'DEF'],
                ],
            ],
        ];

        $indicator = $this->buildIndicator();
        $result = $indicator->prepareDataSource($dataSource);

        $this->assertSame('', $result['data']['items'][0]['scopehint_overrides_5']);
    }

    public function testStoreIdZeroReturnsDataSourceUnchanged(): void
    {
        $this->aggregator->expects($this->never())->method('getOverrideCounts');

        $dataSource = [
            'data' => [
                'items' => [
                    ['entity_id' => '1', 'sku' => 'XYZ'],
                ],
            ],
        ];

        $indicator = $this->buildIndicator(['storeId' => 0], 'scopehint_overrides_0');
        $result = $indicator->prepareDataSource($dataSource);

        $this->assertSame($dataSource, $result);
    }

    public function testMixedItemsSomeWithCountSomeWithout(): void
    {
        $this->aggregator->method('getOverrideCounts')
            ->willReturn([
                10 => [5 => 2],
                // entity 20 has no entry for store 5
            ]);

        $dataSource = [
            'data' => [
                'items' => [
                    ['entity_id' => '10', 'sku' => 'A'],
                    ['entity_id' => '20', 'sku' => 'B'],
                ],
            ],
        ];

        $indicator = $this->buildIndicator(['storeId' => 5], 'scopehint_overrides_5');
        $result = $indicator->prepareDataSource($dataSource);

        $this->assertStringContainsString('>2<', $result['data']['items'][0]['scopehint_overrides_5']);
        $this->assertSame('', $result['data']['items'][1]['scopehint_overrides_5']);
    }
}
