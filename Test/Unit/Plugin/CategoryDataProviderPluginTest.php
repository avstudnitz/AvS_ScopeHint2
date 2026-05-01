<?php

declare(strict_types=1);

namespace AvS\ScopeHint\Test\Unit\Plugin;

use AvS\ScopeHint\Api\Data\OverrideResultInterface;
use AvS\ScopeHint\Api\Data\ScopeOverrideInterface;
use AvS\ScopeHint\Block\Adminhtml\Eav\Popover;
use AvS\ScopeHint\Plugin\CategoryDataProviderPlugin;
use AvS\ScopeHint\Service\OverrideSourcePool;
use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Catalog\Model\Category\DataProvider;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Stdlib\ArrayManager;
use Magento\Framework\View\LayoutInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CategoryDataProviderPluginTest extends TestCase
{
    /** @var OverrideSourcePool&MockObject */
    private $pool;

    /** @var LayoutInterface&MockObject */
    private $layout;

    /** @var ScopeConfigInterface&MockObject */
    private $scopeConfig;

    /** @var DataProvider&MockObject */
    private $subject;

    /** @var CategoryDataProviderPlugin */
    private $plugin;

    protected function setUp(): void
    {
        $this->pool        = $this->createMock(OverrideSourcePool::class);
        $this->layout      = $this->createMock(LayoutInterface::class);
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->subject     = $this->getMockBuilder(DataProvider::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getCurrentCategory'])
            ->getMock();

        $this->plugin = new CategoryDataProviderPlugin(
            $this->pool,
            $this->layout,
            $this->scopeConfig,
            new ArrayManager()
        );
    }

    public function testReturnsResultUnchangedWhenCategoryIsNull(): void
    {
        $this->subject->method('getCurrentCategory')->willReturn(null);

        $meta = $this->buildMeta('general', 'name', ['code' => 'name']);

        $output = $this->plugin->afterGetMeta($this->subject, $meta);

        self::assertSame($meta, $output);
    }

    public function testReturnsResultUnchangedWhenCategoryHasNoId(): void
    {
        $category = $this->createCategory(null);
        $this->subject->method('getCurrentCategory')->willReturn($category);

        $meta = $this->buildMeta('general', 'name', ['code' => 'name']);

        $output = $this->plugin->afterGetMeta($this->subject, $meta);

        self::assertSame($meta, $output);
    }

    public function testReturnsResultUnchangedWhenPoolReturnsNull(): void
    {
        $this->stubCategory(10);
        $this->pool->method('resolve')->willReturn(null);

        $meta = $this->buildMeta('general', 'name', ['code' => 'name']);

        $output = $this->plugin->afterGetMeta($this->subject, $meta);

        // additionalInfo should not be present
        self::assertArrayNotHasKey(
            'additionalInfo',
            $output['general']['children']['name']['arguments']['data']['config']
        );
    }

    public function testReturnsResultUnchangedWhenOverridesAreEmpty(): void
    {
        $this->stubCategory(10);

        $empty = $this->createMock(OverrideResultInterface::class);
        $empty->method('isEmpty')->willReturn(true);
        $this->pool->method('resolve')->willReturn($empty);

        $meta = $this->buildMeta('general', 'name', ['code' => 'name']);

        $output = $this->plugin->afterGetMeta($this->subject, $meta);

        self::assertArrayNotHasKey(
            'additionalInfo',
            $output['general']['children']['name']['arguments']['data']['config']
        );
    }

    public function testSetsAdditionalInfoWhenOverridesExist(): void
    {
        $this->stubCategory(10);
        $this->scopeConfig->method('isSetFlag')->willReturn(false);

        $overrides = $this->createMock(OverrideResultInterface::class);
        $overrides->method('isEmpty')->willReturn(false);
        $this->pool->method('resolve')->willReturn($overrides);

        $popoverBlock = $this->createMock(Popover::class);
        $popoverBlock->method('toHtml')->willReturn('<div data-scopehint-popover="1">POP</div>');
        $popoverBlock->method('setOverrides')->willReturnSelf();
        $this->layout->method('createBlock')->with(Popover::class)->willReturn($popoverBlock);

        $meta = $this->buildMeta('general', 'name', ['code' => 'name']);

        $output = $this->plugin->afterGetMeta($this->subject, $meta);

        self::assertArrayHasKey(
            'additionalInfo',
            $output['general']['children']['name']['arguments']['data']['config']
        );
        self::assertStringContainsString(
            'data-scopehint-popover',
            $output['general']['children']['name']['arguments']['data']['config']['additionalInfo']
        );
    }

    public function testDoesNotSetTooltipInDefaultMode(): void
    {
        $this->stubCategory(10);
        $this->scopeConfig->method('isSetFlag')->willReturn(false);

        $overrides = $this->createMock(OverrideResultInterface::class);
        $overrides->method('isEmpty')->willReturn(false);
        $this->pool->method('resolve')->willReturn($overrides);

        $popoverBlock = $this->createMock(Popover::class);
        $popoverBlock->method('toHtml')->willReturn('<div data-scopehint-popover="1">POP</div>');
        $popoverBlock->method('setOverrides')->willReturnSelf();
        $this->layout->method('createBlock')->willReturn($popoverBlock);

        $meta = $this->buildMeta('general', 'name', ['code' => 'name']);

        $output = $this->plugin->afterGetMeta($this->subject, $meta);

        self::assertArrayNotHasKey(
            'tooltip',
            $output['general']['children']['name']['arguments']['data']['config']
        );
    }

    public function testSetsTooltipDescriptionInLegacyMode(): void
    {
        $this->stubCategory(10);
        $this->scopeConfig->method('isSetFlag')->with('dev/debug/scopehint_legacy_tooltip')->willReturn(true);

        $scopeOverride = $this->createMock(ScopeOverrideInterface::class);
        $scopeOverride->method('getScopeName')->willReturn('German Store');
        $scopeOverride->method('getDisplayValue')->willReturn('Schuhe');

        $overrides = $this->createMock(OverrideResultInterface::class);
        $overrides->method('isEmpty')->willReturn(false);
        $overrides->method('getOverrides')->willReturn([$scopeOverride]);
        $this->pool->method('resolve')->willReturn($overrides);

        $meta = $this->buildMeta('general', 'name', ['code' => 'name']);

        $output = $this->plugin->afterGetMeta($this->subject, $meta);

        $config = $output['general']['children']['name']['arguments']['data']['config'];
        self::assertArrayHasKey('tooltip', $config);
        self::assertStringContainsString('German Store', $config['tooltip']['description']);
        self::assertArrayNotHasKey('additionalInfo', $config);
    }

    public function testPoolReceivesCorrectEntityTypeAndId(): void
    {
        $this->stubCategory(42);
        $this->scopeConfig->method('isSetFlag')->willReturn(false);

        $fieldMeta = ['code' => 'description'];

        $this->pool->expects(self::once())
            ->method('resolve')
            ->with('category', 42, $fieldMeta)
            ->willReturn(null);

        $meta = $this->buildMeta('general', 'description', $fieldMeta);

        $this->plugin->afterGetMeta($this->subject, $meta);
    }

    public function testSkipsFieldsWithNoConfig(): void
    {
        $this->stubCategory(10);

        // Pool should never be called when the field has no arguments/data/config
        $this->pool->expects(self::never())->method('resolve');

        $meta = [
            'general' => [
                'children' => [
                    'name' => [
                        // deliberately missing arguments/data/config
                    ],
                ],
            ],
        ];

        $output = $this->plugin->afterGetMeta($this->subject, $meta);

        self::assertSame($meta, $output);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function stubCategory(int $id): void
    {
        $category = $this->createCategory($id);
        $this->subject->method('getCurrentCategory')->willReturn($category);
    }

    private function createCategory(?int $id): CategoryInterface
    {
        $category = $this->getMockBuilder(CategoryInterface::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
        $category->method('getId')->willReturn($id);

        return $category;
    }

    private function buildMeta(string $fieldsetName, string $fieldName, array $fieldMeta): array
    {
        return [
            $fieldsetName => [
                'children' => [
                    $fieldName => [
                        'arguments' => [
                            'data' => [
                                'config' => $fieldMeta,
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
