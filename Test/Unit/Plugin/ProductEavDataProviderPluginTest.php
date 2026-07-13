<?php

declare(strict_types=1);

namespace AvS\ScopeHint\Test\Unit\Plugin;

use AvS\ScopeHint\Api\Data\OverrideResultInterface;
use AvS\ScopeHint\Api\Data\ScopeOverrideInterface;
use AvS\ScopeHint\Block\Adminhtml\Eav\Popover;
use AvS\ScopeHint\Plugin\ProductEavDataProviderPlugin;
use AvS\ScopeHint\Service\OverrideSourcePool;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Ui\DataProvider\Product\Form\Modifier\Eav;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Escaper;
use Magento\Framework\Registry;
use Magento\Framework\View\LayoutInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ProductEavDataProviderPluginTest extends TestCase
{
    /** @var OverrideSourcePool&MockObject */
    private $pool;

    /** @var LayoutInterface&MockObject */
    private $layout;

    /** @var ScopeConfigInterface&MockObject */
    private $scopeConfig;

    /** @var Registry&MockObject */
    private $registry;

    /** @var Escaper&MockObject */
    private $escaper;

    /** @var Eav&MockObject */
    private $subject;

    /** @var ProductEavDataProviderPlugin */
    private $plugin;

    protected function setUp(): void
    {
        $this->pool        = $this->createMock(OverrideSourcePool::class);
        $this->layout      = $this->createMock(LayoutInterface::class);
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->registry    = $this->createMock(Registry::class);
        $this->escaper     = $this->createMock(Escaper::class);
        $this->escaper->method('escapeHtml')->willReturnCallback(static function ($value) {
            return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        });
        $this->subject     = $this->createMock(Eav::class);

        $this->plugin = new ProductEavDataProviderPlugin(
            $this->pool,
            $this->layout,
            $this->scopeConfig,
            $this->registry,
            $this->escaper
        );
    }

    public function testReturnsResultUnchangedWhenConfigIsMissing(): void
    {
        $result = ['arguments' => []];

        $output = $this->plugin->afterSetupAttributeMeta($this->subject, $result);

        self::assertSame($result, $output);
    }

    public function testReturnsResultUnchangedWhenNoCurrentProduct(): void
    {
        $this->registry->method('registry')->with('current_product')->willReturn(null);

        $result = $this->buildResult(['code' => 'name']);

        $output = $this->plugin->afterSetupAttributeMeta($this->subject, $result);

        self::assertSame($result, $output);
    }

    public function testReturnsResultUnchangedWhenProductIdIsNull(): void
    {
        $product = $this->createMock(ProductInterface::class);
        $product->method('getId')->willReturn(null);
        $this->registry->method('registry')->with('current_product')->willReturn($product);

        $result = $this->buildResult(['code' => 'name']);

        $output = $this->plugin->afterSetupAttributeMeta($this->subject, $result);

        self::assertSame($result, $output);
    }

    public function testReturnsResultUnchangedWhenPoolReturnsNull(): void
    {
        $this->stubProduct(5);
        $this->pool->method('resolve')->willReturn(null);

        $result = $this->buildResult(['code' => 'name']);

        $output = $this->plugin->afterSetupAttributeMeta($this->subject, $result);

        self::assertSame($result, $output);
        self::assertArrayNotHasKey('additionalInfo', $output['arguments']['data']['config']);
    }

    public function testReturnsResultUnchangedWhenOverridesAreEmpty(): void
    {
        $this->stubProduct(5);

        $empty = $this->createMock(OverrideResultInterface::class);
        $empty->method('isEmpty')->willReturn(true);
        $this->pool->method('resolve')->willReturn($empty);

        $result = $this->buildResult(['code' => 'name']);

        $output = $this->plugin->afterSetupAttributeMeta($this->subject, $result);

        self::assertSame($result, $output);
    }

    public function testSetsAdditionalInfoWhenOverridesExist(): void
    {
        $this->stubProduct(5);
        $this->scopeConfig->method('isSetFlag')->willReturn(false);

        $overrides = $this->createMock(OverrideResultInterface::class);
        $overrides->method('isEmpty')->willReturn(false);
        $this->pool->method('resolve')->willReturn($overrides);

        $popoverBlock = $this->createMock(Popover::class);
        $popoverBlock->method('toHtml')->willReturn('<div data-scopehint-popover="1">POP</div>');
        $popoverBlock->method('setOverrides')->willReturnSelf();
        $this->layout->method('createBlock')->with(Popover::class)->willReturn($popoverBlock);

        $result = $this->buildResult(['code' => 'meta_description']);

        $output = $this->plugin->afterSetupAttributeMeta($this->subject, $result);

        self::assertArrayHasKey('additionalInfo', $output['arguments']['data']['config']);
        self::assertStringContainsString('data-scopehint-popover', $output['arguments']['data']['config']['additionalInfo']);
    }

    public function testDoesNotSetTooltipDescriptionInDefaultMode(): void
    {
        $this->stubProduct(5);
        $this->scopeConfig->method('isSetFlag')->willReturn(false);

        $overrides = $this->createMock(OverrideResultInterface::class);
        $overrides->method('isEmpty')->willReturn(false);
        $this->pool->method('resolve')->willReturn($overrides);

        $popoverBlock = $this->createMock(Popover::class);
        $popoverBlock->method('toHtml')->willReturn('<div data-scopehint-popover="1">POP</div>');
        $popoverBlock->method('setOverrides')->willReturnSelf();
        $this->layout->method('createBlock')->willReturn($popoverBlock);

        $result = $this->buildResult(['code' => 'meta_description']);

        $output = $this->plugin->afterSetupAttributeMeta($this->subject, $result);

        self::assertArrayNotHasKey('tooltip', $output['arguments']['data']['config']);
    }

    public function testSetsTooltipDescriptionInLegacyMode(): void
    {
        $this->stubProduct(5);
        $this->scopeConfig->method('isSetFlag')->with('dev/debug/scopehint_legacy_tooltip')->willReturn(true);

        $scopeOverride = $this->createMock(ScopeOverrideInterface::class);
        $scopeOverride->method('getScopeName')->willReturn('German Store');
        $scopeOverride->method('getDisplayValue')->willReturn('Überschriebener Wert');

        $overrides = $this->createMock(OverrideResultInterface::class);
        $overrides->method('isEmpty')->willReturn(false);
        $overrides->method('getOverrides')->willReturn([$scopeOverride]);
        $this->pool->method('resolve')->willReturn($overrides);

        $result = $this->buildResult(['code' => 'name']);

        $output = $this->plugin->afterSetupAttributeMeta($this->subject, $result);

        self::assertArrayHasKey('tooltip', $output['arguments']['data']['config']);
        self::assertStringContainsString(
            'German Store',
            $output['arguments']['data']['config']['tooltip']['description']
        );
        self::assertArrayNotHasKey('additionalInfo', $output['arguments']['data']['config']);
    }

    public function testDoesNotSetAdditionalInfoWhenPopoverHtmlIsEmpty(): void
    {
        $this->stubProduct(5);
        $this->scopeConfig->method('isSetFlag')->willReturn(false);

        $overrides = $this->createMock(OverrideResultInterface::class);
        $overrides->method('isEmpty')->willReturn(false);
        $this->pool->method('resolve')->willReturn($overrides);

        $popoverBlock = $this->createMock(Popover::class);
        $popoverBlock->method('toHtml')->willReturn('');
        $popoverBlock->method('setOverrides')->willReturnSelf();
        $this->layout->method('createBlock')->willReturn($popoverBlock);

        $result = $this->buildResult(['code' => 'name']);

        $output = $this->plugin->afterSetupAttributeMeta($this->subject, $result);

        self::assertArrayNotHasKey('additionalInfo', $output['arguments']['data']['config']);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function stubProduct(int $id): void
    {
        $product = $this->createMock(ProductInterface::class);
        $product->method('getId')->willReturn($id);
        $this->registry->method('registry')->with('current_product')->willReturn($product);
    }

    private function buildResult(array $fieldMeta): array
    {
        return [
            'arguments' => [
                'data' => [
                    'config' => $fieldMeta,
                ],
            ],
        ];
    }
}
