<?php

declare(strict_types=1);

namespace AvS\ScopeHint\Test\Unit\Plugin;

use AvS\ScopeHint\Block\Adminhtml\Catalog\Product\Grid\CellPopover;
use AvS\ScopeHint\Plugin\ProductGridDataProviderPlugin;
use AvS\ScopeHint\Service\ProductOverrideAggregator;
use Magento\Catalog\Ui\DataProvider\Product\ProductDataProvider;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute as CatalogAttribute;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Stdlib\ArrayManager;
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

    /** @var EavConfig&MockObject */
    private $eavConfig;

    /** @var ProductDataProvider&MockObject */
    private $subject;

    /** @var ProductGridDataProviderPlugin */
    private $plugin;

    protected function setUp(): void
    {
        $this->aggregator  = $this->createMock(ProductOverrideAggregator::class);
        $this->cellPopover = $this->createMock(CellPopover::class);
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->eavConfig   = $this->createMock(EavConfig::class);
        $this->subject     = $this->getMockBuilder(ProductDataProvider::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->plugin = new ProductGridDataProviderPlugin(
            $this->aggregator,
            $this->cellPopover,
            $this->scopeConfig,
            $this->eavConfig,
            new ArrayManager()
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

        $this->assertStringContainsString('data-scopehint-popover', $output['items'][0]['name']);
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

    // ------------------------------------------------------------------
    // afterGetMeta tests
    // ------------------------------------------------------------------

    public function testAfterGetMetaReturnsUnchangedWhenLegacyFlagIsSet(): void
    {
        $this->scopeConfig->method('isSetFlag')->willReturn(true);
        $this->eavConfig->expects($this->never())->method('getAttribute');

        $meta = $this->buildMeta('product_columns', 'name');

        $output = $this->plugin->afterGetMeta($this->subject, $meta);

        $this->assertSame($meta, $output);
    }

    public function testAfterGetMetaSetsBodyTmplForNonGlobalAttribute(): void
    {
        $this->scopeConfig->method('isSetFlag')->willReturn(false);

        $attribute = $this->buildAttribute('name', ScopedAttributeInterface::SCOPE_STORE, 'text');
        $this->eavConfig->method('getAttribute')
            ->with('catalog_product', 'name')
            ->willReturn($attribute);

        $meta = $this->buildMeta('product_columns', 'name');

        $output = $this->plugin->afterGetMeta($this->subject, $meta);

        $bodyTmpl = $output['product_columns']['children']['name']['arguments']['data']['config']['bodyTmpl'] ?? null;
        $this->assertSame('ui/grid/cells/html', $bodyTmpl);
    }

    public function testAfterGetMetaDoesNotSetBodyTmplForGlobalAttribute(): void
    {
        $this->scopeConfig->method('isSetFlag')->willReturn(false);

        $attribute = $this->buildAttribute('sku', ScopedAttributeInterface::SCOPE_GLOBAL, 'text');
        $this->eavConfig->method('getAttribute')
            ->with('catalog_product', 'sku')
            ->willReturn($attribute);

        $meta = $this->buildMeta('product_columns', 'sku');

        $output = $this->plugin->afterGetMeta($this->subject, $meta);

        $bodyTmpl = $output['product_columns']['children']['sku']['arguments']['data']['config']['bodyTmpl'] ?? null;
        $this->assertNull($bodyTmpl);
    }

    public function testAfterGetMetaDoesNotSetBodyTmplForImageAttribute(): void
    {
        $this->scopeConfig->method('isSetFlag')->willReturn(false);

        $attribute = $this->buildAttribute('image', ScopedAttributeInterface::SCOPE_STORE, 'image');
        $this->eavConfig->method('getAttribute')
            ->with('catalog_product', 'image')
            ->willReturn($attribute);

        $meta = $this->buildMeta('product_columns', 'image');

        $output = $this->plugin->afterGetMeta($this->subject, $meta);

        $bodyTmpl = $output['product_columns']['children']['image']['arguments']['data']['config']['bodyTmpl'] ?? null;
        $this->assertNull($bodyTmpl);
    }

    public function testAfterGetMetaDoesNotSetBodyTmplForUnknownColumn(): void
    {
        $this->scopeConfig->method('isSetFlag')->willReturn(false);

        // getAttribute returns null (non-EAV column like entity_id)
        $this->eavConfig->method('getAttribute')
            ->with('catalog_product', 'entity_id')
            ->willReturn(null);

        $meta = $this->buildMeta('product_columns', 'entity_id');

        $output = $this->plugin->afterGetMeta($this->subject, $meta);

        $bodyTmpl = $output['product_columns']['children']['entity_id']['arguments']['data']['config']['bodyTmpl'] ?? null;
        $this->assertNull($bodyTmpl);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function buildMeta(string $container, string $columnName): array
    {
        return [
            $container => [
                'children' => [
                    $columnName => [
                        'arguments' => [
                            'data' => [
                                'config' => [],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function buildAttribute(string $code, int $scope, string $frontendInput): CatalogAttribute
    {
        $attribute = $this->createMock(CatalogAttribute::class);
        $attribute->method('getAttributeId')->willReturn('42');
        $attribute->method('getAttributeCode')->willReturn($code);
        $attribute->method('getIsGlobal')->willReturn((string) $scope);
        $attribute->method('getFrontendInput')->willReturn($frontendInput);

        return $attribute;
    }
}
