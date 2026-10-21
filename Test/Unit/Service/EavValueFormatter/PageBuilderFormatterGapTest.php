<?php

declare(strict_types=1);

namespace AvS\ScopeHint\Test\Unit\Service\EavValueFormatter;

use AvS\ScopeHint\Service\EavValueFormatter\PageBuilderFormatter;
use Magento\Framework\Escaper;
use Magento\Framework\Filter\StripTags;
use PHPUnit\Framework\TestCase;

class PageBuilderFormatterGapTest extends TestCase
{
    /** @var PageBuilderFormatter */
    private $formatter;

    protected function setUp(): void
    {
        $stripTags = $this->createMock(StripTags::class);
        $stripTags->method('filter')->willReturnCallback(
            static function ($value) {
                return strip_tags((string) $value);
            }
        );
        $escaper = $this->createMock(Escaper::class);
        $escaper->method('escapeHtml')->willReturnCallback(
            static function ($value) {
                return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
            }
        );
        $this->formatter = new PageBuilderFormatter($stripTags, $escaper);
    }

    public function testMultipleHtmlCodeElementsAreAllDecoded(): void
    {
        $value = '<div data-content-type="html">&lt;p&gt;First&lt;/p&gt;</div>'
            . '<div data-content-type="html">&lt;p&gt;Second&lt;/p&gt;</div>';

        self::assertSame(
            'First Second (Page Builder content differs)',
            $this->formatter->format($value)
        );
    }

    public function testSingleQuotedContentTypeIsDecoded(): void
    {
        $value = "<div data-content-type='html'>&lt;h2&gt;Hallo&lt;/h2&gt;</div>";

        self::assertSame('Hallo (Page Builder content differs)', $this->formatter->format($value));
    }

    public function testHtmlCodeNextToTextElementKeepsLiteralTextOfTheTextElement(): void
    {
        $value = '<div data-content-type="text"><p>Use &lt;value&gt; here</p></div>'
            . '<div data-content-type="html">&lt;p&gt;Code&lt;/p&gt;</div>';

        self::assertSame(
            'Use <value> here Code (Page Builder content differs)',
            html_entity_decode($this->formatter->format($value), ENT_QUOTES, 'UTF-8')
        );
    }

    public function testEncodedScriptInsideHtmlCodeIsNotShown(): void
    {
        $value = '<div data-content-type="html">&lt;script&gt;alert(1)&lt;/script&gt;&lt;p&gt;Safe&lt;/p&gt;</div>';

        $result = $this->formatter->format($value);

        self::assertStringNotContainsString('alert', $result);
        self::assertStringContainsString('Safe', $result);
    }

    public function testHtmlCodeElementWithoutClosingDivDoesNotBreakFormatting(): void
    {
        $result = $this->formatter->format('<div data-content-type="html">&lt;p&gt;Open');

        self::assertStringContainsString('(Page Builder content differs)', $result);
    }
}
