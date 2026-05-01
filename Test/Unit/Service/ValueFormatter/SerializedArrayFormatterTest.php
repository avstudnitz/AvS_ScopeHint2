<?php

declare(strict_types=1);

namespace AvS\ScopeHint\Test\Unit\Service\ValueFormatter;

use AvS\ScopeHint\Service\ValueFormatter\SerializedArrayFormatter;
use Magento\Config\Model\Config\Structure\Element\Field;
use Magento\Framework\Escaper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SerializedArrayFormatterTest extends TestCase
{
    /** @var Escaper&MockObject */
    private $escaper;

    /** @var SerializedArrayFormatter */
    private $formatter;

    protected function setUp(): void
    {
        $this->escaper = $this->createMock(Escaper::class);
        $this->escaper->method('escapeHtml')->willReturnArgument(0);

        $this->formatter = new SerializedArrayFormatter($this->escaper);
    }

    private function fieldWithBackend(string $backendModel): Field
    {
        $field = $this->getMockBuilder(Field::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getBackendModel'])
            ->getMock();
        $field->method('getBackendModel')->willReturn($backendModel);

        return $field;
    }

    public function testSupportsSerializedBackendModel(): void
    {
        $field = $this->fieldWithBackend('Magento\Config\Model\Config\Backend\Serialized\ArraySerialized');

        self::assertTrue($this->formatter->supports($field));
    }

    public function testSupportsFieldArrayBackend(): void
    {
        $field = $this->fieldWithBackend('Magento\Config\Model\Config\Backend\Serialized');

        self::assertTrue($this->formatter->supports($field));
    }

    public function testDoesNotSupportEmptyBackend(): void
    {
        $field = $this->fieldWithBackend('');

        self::assertFalse($this->formatter->supports($field));
    }

    public function testFormatArrayValueReturnsRowCount(): void
    {
        $field = $this->fieldWithBackend('Magento\Config\Model\Config\Backend\Serialized');

        $result = $this->formatter->format(['row1' => [], 'row2' => [], 'row3' => []], $field);

        self::assertStringContainsString('3', $result);
        self::assertStringContainsString('rows configured', $result);
    }

    public function testFormatJsonStringReturnsRowCount(): void
    {
        $field = $this->fieldWithBackend('Magento\Config\Model\Config\Backend\Serialized');
        $json = json_encode([['price' => 10], ['price' => 20]]);

        $result = $this->formatter->format($json, $field);

        self::assertStringContainsString('2', $result);
        self::assertStringContainsString('rows configured', $result);
    }

    public function testFormatInvalidStringReturnsFallback(): void
    {
        $field = $this->fieldWithBackend('Magento\Config\Model\Config\Backend\Serialized');

        $result = $this->formatter->format('not-serialized-at-all!!!', $field);

        self::assertStringContainsString('serialized data differs', $result);
    }
}
