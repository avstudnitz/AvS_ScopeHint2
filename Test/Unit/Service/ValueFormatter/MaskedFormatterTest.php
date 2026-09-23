<?php

declare(strict_types=1);

namespace AvS\ScopeHint\Test\Unit\Service\ValueFormatter;

use AvS\ScopeHint\Service\ValueFormatter\MaskedFormatter;
use Magento\Config\Model\Config\Structure\Element\Field;
use PHPUnit\Framework\TestCase;

class MaskedFormatterTest extends TestCase
{
    /** @var MaskedFormatter */
    private $formatter;

    protected function setUp(): void
    {
        $this->formatter = new MaskedFormatter();
    }

    public function testSupportsPasswordType(): void
    {
        $field = $this->createMock(Field::class);
        $field->method('getType')->willReturn('password');

        self::assertTrue($this->formatter->supports($field));
    }

    public function testSupportsObscureType(): void
    {
        $field = $this->createMock(Field::class);
        $field->method('getType')->willReturn('obscure');

        self::assertTrue($this->formatter->supports($field));
    }

    public function testDoesNotSupportOtherTypes(): void
    {
        $field = $this->createMock(Field::class);
        $field->method('getType')->willReturn('text');

        self::assertFalse($this->formatter->supports($field));
    }

    public function testFormatNonEmptyValueReturnsBullets(): void
    {
        $field = $this->createMock(Field::class);

        $result = $this->formatter->format('secret', $field);

        self::assertSame('●●●●●●', $result);
    }

    public function testFormatEmptyValueReturnsEmptyString(): void
    {
        $field = $this->createMock(Field::class);

        self::assertSame('', $this->formatter->format('', $field));
        self::assertSame('', $this->formatter->format(null, $field));
    }

    public function testFormatDoesNotLeakValueLength(): void
    {
        $field = $this->createMock(Field::class);

        $short = $this->formatter->format('x', $field);
        $long = $this->formatter->format('averylongpassword', $field);

        self::assertSame($short, $long);
    }
}
