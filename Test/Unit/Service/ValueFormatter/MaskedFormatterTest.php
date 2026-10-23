<?php

declare(strict_types=1);

namespace AvS\ScopeHint\Test\Unit\Service\ValueFormatter;

use AvS\ScopeHint\Service\ValueFormatter\MaskedFormatter;
use Magento\Config\Model\Config\Backend\Encrypted;
use Magento\Config\Model\Config\Structure\Element\Field;
use Magento\Config\Model\Config\TypePool;
use Magento\Framework\App\Config\Value;
use Magento\Framework\ObjectManager\ConfigInterface as DiConfig;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class MaskedFormatterTest extends TestCase
{
    /** @var TypePool&MockObject */
    private $typePool;

    /** @var DiConfig&MockObject */
    private $diConfig;

    /** @var MaskedFormatter */
    private $formatter;

    protected function setUp(): void
    {
        $this->typePool = $this->createMock(TypePool::class);
        $this->diConfig = $this->createMock(DiConfig::class);
        $this->diConfig->method('getInstanceType')->willReturnArgument(0);

        $this->formatter = new MaskedFormatter($this->typePool, $this->diConfig);
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

    public function testSupportsSensitivePath(): void
    {
        $field = $this->createMock(Field::class);
        $field->method('getType')->willReturn('text');
        $field->method('getConfigPath')->willReturn('payment/foo/api_key');

        $this->typePool->method('isPresent')
            ->with('payment/foo/api_key', TypePool::TYPE_SENSITIVE)
            ->willReturn(true);

        self::assertTrue($this->formatter->supports($field));
    }

    public function testSupportsEncryptedBackendModel(): void
    {
        $field = $this->createMock(Field::class);
        $field->method('getType')->willReturn('text');
        $field->method('getAttribute')->with('backend_model')->willReturn('\\' . Encrypted::class);

        self::assertTrue($this->formatter->supports($field));
    }

    public function testSupportsVirtualTypeOfEncryptedBackendModel(): void
    {
        $diConfig = $this->createMock(DiConfig::class);
        $diConfig->method('getInstanceType')
            ->with('Vendor\Module\Model\Config\Backend\SecretVirtual')
            ->willReturn(Encrypted::class);
        $formatter = new MaskedFormatter($this->typePool, $diConfig);

        $field = $this->createMock(Field::class);
        $field->method('getType')->willReturn('text');
        $field->method('getAttribute')->with('backend_model')
            ->willReturn('Vendor\Module\Model\Config\Backend\SecretVirtual');

        self::assertTrue($formatter->supports($field));
    }

    public function testDoesNotSupportOtherBackendModel(): void
    {
        $field = $this->createMock(Field::class);
        $field->method('getType')->willReturn('text');
        $field->method('getAttribute')->with('backend_model')->willReturn(Value::class);

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
