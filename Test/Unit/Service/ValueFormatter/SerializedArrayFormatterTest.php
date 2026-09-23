<?php

declare(strict_types=1);

namespace AvS\ScopeHint\Test\Unit\Service\ValueFormatter;

use AvS\ScopeHint\Service\ValueFormatter\SerializedArrayFormatter;
use Magento\Config\Model\Config\BackendFactory;
use Magento\Config\Model\Config\CommentFactory;
use Magento\Config\Model\Config\SourceFactory;
use Magento\Config\Model\Config\Structure\Element\Dependency\Mapper;
use Magento\Config\Model\Config\Structure\Element\Field;
use Magento\Framework\Escaper;
use Magento\Framework\Module\Manager as ModuleManager;
use Magento\Framework\View\Element\BlockFactory;
use Magento\Store\Model\StoreManagerInterface;
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

    public function testSupportsArraySerializedBackendModel(): void
    {
        $field = $this->createField(['backend_model' => 'Magento\Config\Model\Config\Backend\Serialized\ArraySerialized']);

        self::assertTrue($this->formatter->supports($field));
    }

    public function testSupportsSerializedBackendModel(): void
    {
        $field = $this->createField(['backend_model' => 'Magento\Config\Model\Config\Backend\Serialized']);

        self::assertTrue($this->formatter->supports($field));
    }

    public function testDoesNotSupportUnrelatedBackendModel(): void
    {
        $field = $this->createField(['backend_model' => 'Magento\Config\Model\Config\Backend\Email\Address']);

        self::assertFalse($this->formatter->supports($field));
    }

    public function testDoesNotSupportFieldWithoutBackendModel(): void
    {
        $field = $this->createField([]);

        self::assertFalse($this->formatter->supports($field));
    }

    public function testFormatArrayValueReturnsRowCount(): void
    {
        $field = $this->createField(['backend_model' => 'Magento\Config\Model\Config\Backend\Serialized']);

        $result = $this->formatter->format(['row1' => [], 'row2' => [], 'row3' => []], $field);

        self::assertStringContainsString('3', $result);
        self::assertStringContainsString('rows configured', $result);
    }

    public function testFormatJsonStringReturnsRowCount(): void
    {
        $field = $this->createField(['backend_model' => 'Magento\Config\Model\Config\Backend\Serialized']);
        $json = json_encode([['price' => 10], ['price' => 20]]);

        $result = $this->formatter->format($json, $field);

        self::assertStringContainsString('2', $result);
        self::assertStringContainsString('rows configured', $result);
    }

    public function testFormatInvalidStringReturnsFallback(): void
    {
        $field = $this->createField(['backend_model' => 'Magento\Config\Model\Config\Backend\Serialized']);

        $result = $this->formatter->format('not-serialized-at-all!!!', $field);

        self::assertStringContainsString('serialized data differs', $result);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createField(array $data): Field
    {
        $field = new Field(
            $this->createMock(StoreManagerInterface::class),
            $this->createMock(ModuleManager::class),
            $this->createMock(BackendFactory::class),
            $this->createMock(SourceFactory::class),
            $this->createMock(CommentFactory::class),
            $this->createMock(BlockFactory::class),
            $this->createMock(Mapper::class)
        );
        $field->setData($data + ['id' => 'serialized', 'type' => 'text'], 'default');

        return $field;
    }
}
