<?php

declare(strict_types=1);

namespace AvS\ScopeHint\Test\Unit\Service\ValueFormatter;

use AvS\ScopeHint\Service\ValueFormatter\EditorFormatter;
use Magento\Config\Model\Config\Structure\Element\Field;
use Magento\Framework\Escaper;
use Magento\Framework\Filter\StripTags;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class EditorFormatterTest extends TestCase
{
    /** @var StripTags&MockObject */
    private $stripTags;

    /** @var Escaper&MockObject */
    private $escaper;

    /** @var EditorFormatter */
    private $formatter;

    protected function setUp(): void
    {
        $this->stripTags = $this->createMock(StripTags::class);
        $this->stripTags->method('filter')->willReturnCallback(static function ($v) {
            return strip_tags((string) $v);
        });
        $this->escaper = $this->createMock(Escaper::class);
        $this->escaper->method('escapeHtml')->willReturnArgument(0);

        $this->formatter = new EditorFormatter($this->stripTags, $this->escaper);
    }

    public function testSupportsEditorType(): void
    {
        $field = $this->createMock(Field::class);
        $field->method('getType')->willReturn('editor');
        $field->method('getData')->with('frontend_model')->willReturn(null);

        self::assertTrue($this->formatter->supports($field));
    }

    public function testSupportsPagebuilderFrontendModel(): void
    {
        $field = $this->createMock(Field::class);
        $field->method('getType')->willReturn('textarea');
        $field->method('getData')->with('frontend_model')->willReturn('Magento\PageBuilder\Block\Form\Element\Pagebuilder');

        self::assertTrue($this->formatter->supports($field));
    }

    public function testDoesNotSupportTextType(): void
    {
        $field = $this->createMock(Field::class);
        $field->method('getType')->willReturn('text');
        $field->method('getData')->with('frontend_model')->willReturn(null);

        self::assertFalse($this->formatter->supports($field));
    }

    public function testFormatStripsHtmlTags(): void
    {
        $field = $this->createMock(Field::class);

        $result = $this->formatter->format('<p>Hello <b>world</b></p>', $field);

        self::assertStringContainsString('Hello world', $result);
        self::assertStringNotContainsString('<p>', $result);
    }

    public function testFormatTruncatesLongContent(): void
    {
        $field = $this->createMock(Field::class);
        $longText = str_repeat('a', 100);

        $result = $this->formatter->format($longText, $field);

        self::assertStringContainsString('...', $result);
        // 80 chars + '...' + ' (HTML content differs)' prefix
        self::assertStringStartsWith(str_repeat('a', 80) . '...', $result);
    }

    public function testFormatAppendsHtmlContentDiffersLabel(): void
    {
        $field = $this->createMock(Field::class);

        $result = $this->formatter->format('<p>Short</p>', $field);

        self::assertStringContainsString('(HTML content differs)', $result);
    }

    public function testFormatEmptyValueReturnsEmptyString(): void
    {
        $field = $this->createMock(Field::class);

        self::assertSame('', $this->formatter->format('', $field));
        self::assertSame('', $this->formatter->format(null, $field));
    }

    public function testFormatShortContentIsNotTruncated(): void
    {
        $field = $this->createMock(Field::class);
        $short = 'Hello world';

        $result = $this->formatter->format($short, $field);

        self::assertStringStartsWith($short, $result);
        self::assertStringNotContainsString('...', $result);
    }
}
