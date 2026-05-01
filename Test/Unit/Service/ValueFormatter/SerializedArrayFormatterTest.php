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
            ->onlyMethods(['getData'])
            ->getMock();
        $field->method('getData')->willReturnCallback(static function ($key) use ($backendModel) {
            return $key === 'backend_model' ? $backendModel : null;
        });

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

    public function testSupportsObjectBackendInstance(): void
    {
        // Magento sometimes returns an instantiated backend model object instead of a FQCN
        // string. The formatter must handle both cases via instanceof.
        $unrelatedBackend = new \stdClass();
        $serializedBackend = $this->createMock(\Magento\Config\Model\Config\Backend\Serialized\ArraySerialized::class);

        $field1 = $this->fieldWithBackendObject($serializedBackend);
        self::assertTrue($this->formatter->supports($field1));

        $field2 = $this->fieldWithBackendObject($unrelatedBackend);
        self::assertFalse($this->formatter->supports($field2));
    }

    public function testHandlesMissingBackendModelKey(): void
    {
        // Field::getBackendModel() in Magento warns "Undefined array key 'backend_model'"
        // when the field has no backend model. Using getData() instead returns null safely.
        $field = $this->getMockBuilder(Field::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getData'])
            ->getMock();
        $field->method('getData')->willReturn(null);

        self::assertFalse($this->formatter->supports($field));
    }

    private function fieldWithBackendObject(object $backend): Field
    {
        $field = $this->getMockBuilder(Field::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getData'])
            ->getMock();
        $field->method('getData')->willReturnCallback(static function ($key) use ($backend) {
            return $key === 'backend_model' ? $backend : null;
        });
        return $field;
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
