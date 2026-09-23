<?php

declare(strict_types=1);

namespace AvS\ScopeHint\Test\Unit\Service\ValueFormatter;

use AvS\ScopeHint\Service\ValueFormatter\FileFormatter;
use Magento\Config\Model\Config\Structure\Element\Field;
use Magento\Framework\Escaper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class FileFormatterTest extends TestCase
{
    /** @var Escaper&MockObject */
    private $escaper;

    /** @var FileFormatter */
    private $formatter;

    protected function setUp(): void
    {
        $this->escaper = $this->createMock(Escaper::class);
        $this->escaper->method('escapeHtml')->willReturnArgument(0);

        $this->formatter = new FileFormatter($this->escaper);
    }

    public function testSupportsFileType(): void
    {
        $field = $this->createMock(Field::class);
        $field->method('getType')->willReturn('file');

        self::assertTrue($this->formatter->supports($field));
    }

    public function testSupportsImageType(): void
    {
        $field = $this->createMock(Field::class);
        $field->method('getType')->willReturn('image');

        self::assertTrue($this->formatter->supports($field));
    }

    public function testSupportsImagefileType(): void
    {
        $field = $this->createMock(Field::class);
        $field->method('getType')->willReturn('imagefile');

        self::assertTrue($this->formatter->supports($field));
    }

    public function testDoesNotSupportTextType(): void
    {
        $field = $this->createMock(Field::class);
        $field->method('getType')->willReturn('text');

        self::assertFalse($this->formatter->supports($field));
    }

    public function testFormatReturnsBasename(): void
    {
        $field = $this->createMock(Field::class);

        $result = $this->formatter->format('/media/catalog/product/t/e/test-image.jpg', $field);

        self::assertSame('test-image.jpg', $result);
    }

    public function testFormatJustFilename(): void
    {
        $field = $this->createMock(Field::class);

        $result = $this->formatter->format('logo.png', $field);

        self::assertSame('logo.png', $result);
    }

    public function testFormatEmptyValueReturnsEmptyString(): void
    {
        $field = $this->createMock(Field::class);

        self::assertSame('', $this->formatter->format('', $field));
        self::assertSame('', $this->formatter->format(null, $field));
    }
}
