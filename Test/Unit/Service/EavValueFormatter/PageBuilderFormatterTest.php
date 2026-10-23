<?php

declare(strict_types=1);

namespace AvS\ScopeHint\Test\Unit\Service\EavValueFormatter;

use AvS\ScopeHint\Service\EavValueFormatter\PageBuilderFormatter;
use Magento\Framework\Escaper;
use Magento\Framework\Filter\StripTags;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PageBuilderFormatterTest extends TestCase
{
    /** @var StripTags&MockObject */
    private $stripTags;

    /** @var Escaper&MockObject */
    private $escaper;

    /** @var PageBuilderFormatter */
    private $formatter;

    protected function setUp(): void
    {
        $this->stripTags = $this->createMock(StripTags::class);
        $this->stripTags->method('filter')->willReturnCallback(static function ($value) {
            return strip_tags((string) $value);
        });

        $this->escaper = $this->createMock(Escaper::class);
        $this->escaper->method('escapeHtml')->willReturnCallback(static function ($value) {
            return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        });

        $this->formatter = new PageBuilderFormatter($this->stripTags, $this->escaper);
    }

    public function testSupportsMagentoPageBuilderWysiwygMetadata(): void
    {
        self::assertTrue($this->formatter->supports([
            'wysiwygConfigData' => ['is_pagebuilder_enabled' => true],
        ]));
    }

    public function testSupportsDirectCompatibilityFlag(): void
    {
        self::assertTrue($this->formatter->supports(['is_pagebuilder_enabled' => '1']));
    }

    public function testDoesNotSupportDisabledOrMalformedMetadata(): void
    {
        self::assertFalse($this->formatter->supports([]));
        self::assertFalse($this->formatter->supports([
            'wysiwygConfigData' => ['is_pagebuilder_enabled' => false],
        ]));
        self::assertFalse($this->formatter->supports(['wysiwygConfigData' => 'invalid']));
    }

    public function testFormatsPageBuilderMarkupAsNormalizedTextPreview(): void
    {
        $value = '<div data-content-type="text"><p>Hello&nbsp; <strong>world</strong></p>'
            . '<p>Second &amp; final line</p></div>';

        $result = $this->formatter->format($value);

        self::assertSame(
            'Hello world Second &amp; final line (Page Builder content differs)',
            $result
        );
        self::assertStringNotContainsString('data-content-type', $result);
        self::assertStringNotContainsString('&lt;div', $result);
    }

    public function testTruncatesLongPageBuilderContent(): void
    {
        $result = $this->formatter->format('<p>' . str_repeat('a', 100) . '</p>');

        self::assertStringStartsWith(str_repeat('a', 80) . '...', $result);
        self::assertStringEndsWith('(Page Builder content differs)', $result);
    }

    public function testTruncatesMultibyteContentByCharacter(): void
    {
        $result = $this->formatter->format('<p>' . str_repeat('é', 81) . '</p>');

        self::assertStringStartsWith(str_repeat('é', 80) . '...', $result);
    }

    public function testRemovesScriptAndStyleContentsAndEscapesDecodedText(): void
    {
        $result = $this->formatter->format(
            '<style>.secret { display: block }</style><script>alert("x")</script>'
            . '<p>Safe &lt;value&gt; &amp; text</p>'
        );

        self::assertSame(
            'Safe &lt;value&gt; &amp; text (Page Builder content differs)',
            $result
        );
        self::assertStringNotContainsString('secret', $result);
        self::assertStringNotContainsString('alert', $result);
    }

    public function testMarkupWithoutTextStillExplainsTheDifference(): void
    {
        self::assertSame(
            '(Page Builder content differs)',
            $this->formatter->format('<div data-content-type="empty"></div>')
        );
    }

    public function testEmptyValueStillExplainsTheDifference(): void
    {
        self::assertSame('(Page Builder content differs)', $this->formatter->format(''));
        self::assertSame('(Page Builder content differs)', $this->formatter->format(null));
    }

    public function testNonScalarValueReturnsSafeFallback(): void
    {
        self::assertSame(
            '(Page Builder content differs)',
            $this->formatter->format(['unexpected' => 'value'])
        );
    }

    public function testHtmlCodeContentTypeShowsTextNotEncodedMarkup(): void
    {
        $value = '<div data-content-type="html" data-appearance="default" data-element="main">'
            . '&lt;div class=&quot;hyva-card p-12&quot;&gt;&lt;h2&gt;Warum Checkout?&lt;/h2&gt;'
            . '&lt;p&gt;Schnell &amp;amp; einfach&lt;/p&gt;&lt;script&gt;alert(1)&lt;/script&gt;&lt;/div&gt;</div>';

        self::assertSame(
            'Warum Checkout? Schnell &amp; einfach (Page Builder content differs)',
            $this->formatter->format($value)
        );
    }
}
