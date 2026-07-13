<?php

declare(strict_types=1);

namespace AvS\ScopeHint\Test\Unit\Block\Adminhtml\Catalog\Product\Grid;

use AvS\ScopeHint\Block\Adminhtml\Catalog\Product\Grid\CellPopover;
use Magento\Framework\Escaper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CellPopoverTest extends TestCase
{
    /** @var Escaper&MockObject */
    private $escaper;

    /** @var CellPopover */
    private $block;

    protected function setUp(): void
    {
        $this->escaper = $this->createMock(Escaper::class);
        // Identity escaping — return value unchanged so assertions can use plain strings.
        $this->escaper->method('escapeHtml')->willReturnArgument(0);
        $this->escaper->method('escapeHtmlAttr')->willReturnArgument(0);

        $this->block = new CellPopover($this->escaper);
    }

    public function testRenderReturnsEmptyMarkupWhenNoOverrides(): void
    {
        $result = $this->block->render([]);

        $this->assertSame('', $result);
    }

    public function testRenderContainsDataScopehintPopoverAttribute(): void
    {
        $overrides = [
            2 => [
                'displayValue' => 'Overridden Name',
                'storeName'    => 'English Store',
                'storeCode'    => 'en',
                'websiteCode'  => 'base',
            ],
        ];

        $result = $this->block->render($overrides);

        $this->assertStringContainsString('data-scopehint-popover="1"', $result);
    }

    public function testRenderDoesNotDuplicateDefaultValue(): void
    {
        $overrides = [
            2 => [
                'displayValue' => 'Overridden',
                'storeName'    => 'Store A',
                'storeCode'    => 'store_a',
                'websiteCode'  => 'base',
            ],
        ];

        $result = $this->block->render($overrides);

        $this->assertStringNotContainsString('My Default', $result);
    }

    public function testRenderContainsStoreName(): void
    {
        $overrides = [
            3 => [
                'displayValue' => 'Valeur fr',
                'storeName'    => 'French Store',
                'storeCode'    => 'fr',
                'websiteCode'  => 'base',
            ],
        ];

        $result = $this->block->render($overrides);

        $this->assertStringContainsString('French Store', $result);
    }

    public function testRenderContainsBadgeWithCount(): void
    {
        $overrides = [
            2 => [
                'displayValue' => 'Value A',
                'storeName'    => 'Store A',
                'storeCode'    => 'a',
                'websiteCode'  => 'base',
            ],
            3 => [
                'displayValue' => 'Value B',
                'storeName'    => 'Store B',
                'storeCode'    => 'b',
                'websiteCode'  => 'base',
            ],
        ];

        $result = $this->block->render($overrides);

        $this->assertStringContainsString('class="scopehint-badge"', $result);
        $this->assertStringContainsString('>2<', $result);
    }

    public function testRenderCallsEscapeHtmlOnStoreName(): void
    {
        $overrides = [
            2 => [
                'displayValue' => 'Val',
                'storeName'    => 'Store',
                'storeCode'    => 's',
                'websiteCode'  => 'base',
            ],
        ];

        // escapeHtml must be called at least for the storeName value.
        $this->escaper->expects($this->atLeastOnce())
            ->method('escapeHtml')
            ->willReturnArgument(0);

        $result = $this->block->render($overrides);

        $this->assertStringContainsString('data-scopehint-popover="1"', $result);
    }
}
