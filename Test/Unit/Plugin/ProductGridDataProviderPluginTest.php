<?php

declare(strict_types=1);

namespace AvS\ScopeHint\Test\Unit\Plugin;

use AvS\ScopeHint\Block\Adminhtml\Catalog\Product\Grid\CellPopover;
use AvS\ScopeHint\Plugin\ProductGridDataProviderPlugin;
use AvS\ScopeHint\Service\ProductOverrideAggregator;
use Magento\Catalog\Ui\DataProvider\Product\ProductDataProvider;
use Magento\Framework\App\Config\ScopeConfigInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ProductGridDataProviderPluginTest extends TestCase
{
    /** @var ProductOverrideAggregator&MockObject */
    private $aggregator;

    /** @var CellPopover&MockObject */
    private $cellPopover;

    /** @var ScopeConfigInterface&MockObject */
    private $scopeConfig;

    /** @var ProductDataProvider&MockObject */
    private $subject;

    /** @var ProductGridDataProviderPlugin */
    private $plugin;

    protected function setUp(): void
    {
        $this->aggregator  = $this->createMock(ProductOverrideAggregator::class);
        $this->cellPopover = $this->createMock(CellPopover::class);
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->subject     = $this->getMockBuilder(ProductDataProvider::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->plugin = new ProductGridDataProviderPlugin(
            $this->aggregator,
            $this->cellPopover,
            $this->scopeConfig
        );
    }

    // ------------------------------------------------------------------
    // afterGetData tests
    // ------------------------------------------------------------------

    public function testAfterGetDataReturnsUnchangedWhenLegacyFlagIsSet(): void
    {
        $this->scopeConfig->method('isSetFlag')->willReturn(true);
        $this->aggregator->expects($this->never())->method('getOverrides');

        $result = ['items' => [['entity_id' => 1, 'name' => 'Foo']]];

        $output = $this->plugin->afterGetData($this->subject, $result);

        $this->assertSame($result, $output);
    }

    public function testAfterGetDataReturnsUnchangedWhenItemsEmpty(): void
    {
        $this->scopeConfig->method('isSetFlag')->willReturn(false);
        $this->aggregator->expects($this->never())->method('getOverrides');

        $result = ['items' => []];

        $output = $this->plugin->afterGetData($this->subject, $result);

        $this->assertSame($result, $output);
    }

    public function testAfterGetDataEnrichesItemWhenOverrideExists(): void
    {
        $this->scopeConfig->method('isSetFlag')->willReturn(false);

        $this->aggregator->method('getOverrides')
            ->with([1])
            ->willReturn([
                1 => [
                    'name' => [
                        2 => [
                            'displayValue' => 'Overridden',
                            'storeName'    => 'Store A',
                            'storeCode'    => 'a',
                            'websiteCode'  => 'base',
                        ],
                    ],
                ],
            ]);

        $this->cellPopover->method('render')
            ->willReturn('<span class="scopehint-cell-default">Default</span><span data-scopehint-popover="1">PILL</span>');

        $result = [
            'items' => [
                ['entity_id' => '1', 'name' => 'Default'],
            ],
        ];

        $output = $this->plugin->afterGetData($this->subject, $result);

        $this->assertSame('Default', $output['items'][0]['name']);
        $this->assertStringContainsString(
            'data-scopehint-popover',
            $output['items'][0]['__scopehint_name']
        );
    }

    public function testAfterGetDataSkipsColumnNotPresentInItem(): void
    {
        $this->scopeConfig->method('isSetFlag')->willReturn(false);

        $this->aggregator->method('getOverrides')
            ->willReturn([
                1 => [
                    'meta_description' => [
                        2 => [
                            'displayValue' => 'Fr description',
                            'storeName'    => 'French',
                            'storeCode'    => 'fr',
                            'websiteCode'  => 'base',
                        ],
                    ],
                ],
            ]);

        $this->cellPopover->expects($this->never())->method('render');

        // meta_description is NOT in the item keys
        $result = [
            'items' => [
                ['entity_id' => '1', 'name' => 'My Product'],
            ],
        ];

        $output = $this->plugin->afterGetData($this->subject, $result);

        // item should be unchanged
        $this->assertSame('My Product', $output['items'][0]['name']);
        $this->assertArrayNotHasKey('meta_description', $output['items'][0]);
    }

}
