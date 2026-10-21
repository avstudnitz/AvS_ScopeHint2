<?php

declare(strict_types=1);

namespace AvS\ScopeHint\Test\Unit\Service\EavValueFormatter;

use AvS\ScopeHint\Service\EavValueFormatter\PageBuilderFormatter;
use Magento\Framework\Escaper;
use Magento\Framework\Filter\StripTags;
use PHPUnit\Framework\TestCase;

class PageBuilderFormatterBlindTest extends TestCase
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

    private function htmlCodeWrap(string $encodedContent): string
    {
        return '<div data-content-type="html" data-appearance="default" data-element="main">'
            . $encodedContent . '</div>';
    }

    private function germanHtmlCode(): string
    {
        return $this->htmlCodeWrap(
            '&lt;div class=&quot;hyva-card p-12&quot;&gt;&lt;h2 class=&quot;text-2xl&quot;&gt;'
            . 'Warum Checkout?&lt;/h2&gt;&lt;p&gt;Schneller Checkout.&lt;/p&gt;&lt;/div&gt;'
        );
    }

    public function testC2_1HtmlCodeShowsTextExcerpt(): void
    {
        self::assertSame(
            'Warum Checkout? Schneller Checkout. (Page Builder content differs)',
            $this->formatter->format($this->germanHtmlCode())
        );
    }

    public function testC2_2DifferentHtmlCodeTextGivesDifferentPreviews(): void
    {
        $german = $this->formatter->format($this->germanHtmlCode());
        $english = $this->formatter->format($this->htmlCodeWrap(
            '&lt;div class=&quot;hyva-card p-12&quot;&gt;&lt;h2 class=&quot;text-2xl&quot;&gt;'
            . 'Why Checkout?&lt;/h2&gt;&lt;/div&gt;'
        ));

        self::assertNotSame($german, $english);
        self::assertStringContainsString('Warum Checkout?', $german);
        self::assertStringContainsString('Why Checkout?', $english);
    }

    public function testC2_3ScriptIsNotShown(): void
    {
        $result = $this->formatter->format(
            $this->htmlCodeWrap('&lt;script&gt;alert(1)&lt;/script&gt;Visible')
        );

        self::assertStringContainsString('Visible', $result);
        self::assertStringNotContainsString('alert', $result);
        self::assertStringNotContainsString('script', $result);
    }

    public function testC2_4EncodedAmpersandIsShownOnce(): void
    {
        $result = $this->formatter->format($this->htmlCodeWrap('Fast &amp;amp; simple'));

        self::assertStringContainsString('Fast &amp; simple', $result);
        self::assertStringNotContainsString('&amp;amp;', $result);
    }

    public function testC2_5LiteralTextInNormalElementIsKept(): void
    {
        $result = $this->formatter->format(
            '<div data-content-type="text"><p>Use &lt;value&gt; here</p></div>'
        );

        self::assertStringContainsString('Use &lt;value&gt; here', $result);
    }

    public function testC2_6NoPreviewContainsMarkup(): void
    {
        $inputs = [
            $this->germanHtmlCode(),
            $this->htmlCodeWrap('&lt;script&gt;alert(1)&lt;/script&gt;Visible'),
            $this->htmlCodeWrap('Fast &amp;amp; simple'),
            '<div data-content-type="text"><p>Use &lt;value&gt; here</p></div>',
        ];

        foreach ($inputs as $input) {
            $result = $this->formatter->format($input);
            foreach (['<div', '<h2', '&lt;div', 'data-content-type'] as $forbidden) {
                self::assertStringNotContainsString($forbidden, $result);
            }
        }
    }
}
