<?php

declare(strict_types=1);

namespace AvS\ScopeHint\Test\Unit\Service;

use AvS\ScopeHint\Service\OptionLabelResolver;
use Magento\Config\Model\Config\BackendFactory;
use Magento\Config\Model\Config\CommentFactory;
use Magento\Config\Model\Config\SourceFactory;
use Magento\Config\Model\Config\Structure\Element\Dependency\Mapper;
use Magento\Config\Model\Config\Structure\Element\Field;
use Magento\Framework\Module\Manager as ModuleManager;
use Magento\Framework\View\Element\BlockFactory;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class OptionLabelResolverTest extends TestCase
{
    /** @var SourceFactory&MockObject */
    private $sourceFactory;

    /** @var LoggerInterface&MockObject */
    private $logger;

    /** @var OptionLabelResolver */
    private $resolver;

    protected function setUp(): void
    {
        $this->sourceFactory = $this->createMock(SourceFactory::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->resolver = new OptionLabelResolver($this->logger);
    }

    public function testResolvesLabelFromInlineOptions(): void
    {
        $field = $this->createFieldWithInlineOptions([
            ['value' => '0', 'label' => 'No'],
            ['value' => '1', 'label' => 'Yes'],
        ]);

        self::assertSame('Yes', $this->resolver->resolve($field, '1'));
    }

    public function testReturnsRawValueWhenNoMatchInInlineOptions(): void
    {
        $field = $this->createFieldWithInlineOptions([
            ['value' => '0', 'label' => 'No'],
        ]);

        self::assertSame('99', $this->resolver->resolve($field, '99'));
    }

    public function testResolvesMultiselectWithInlineOptions(): void
    {
        $field = $this->createFieldWithInlineOptions([
            ['value' => '0', 'label' => 'No'],
            ['value' => '1', 'label' => 'Yes'],
            ['value' => '2', 'label' => 'Maybe'],
        ], 'multiselect');

        self::assertSame('No, Yes, Maybe', $this->resolver->resolve($field, '0,1,2'));
    }

    public function testReturnsSingleValueUnchangedForNonSelectField(): void
    {
        $field = $this->createField(['id' => 'plain', 'type' => 'text']);

        self::assertSame('some text', $this->resolver->resolve($field, 'some text'));
    }

    public function testResolvesLabelFromSourceModel(): void
    {
        $this->sourceFactory
            ->expects(self::once())
            ->method('create')
            ->with('Magento\Config\Model\Config\Source\Yesno')
            ->willReturn($this->createSourceModel([
                ['value' => '0', 'label' => 'No'],
                ['value' => '1', 'label' => 'Yes'],
            ]));

        $field = $this->createField([
            'id' => 'enabled',
            'type' => 'select',
            'source_model' => 'Magento\Config\Model\Config\Source\Yesno',
        ]);

        self::assertSame('No', $this->resolver->resolve($field, '0'));
    }

    public function testResolvesLabelInsideOptgroup(): void
    {
        $this->sourceFactory->method('create')->willReturn($this->createSourceModel([
            ['label' => 'Europe', 'value' => [
                ['value' => 'Europe/Berlin', 'label' => 'Berlin'],
            ]],
        ]));

        $field = $this->createField(['id' => 'timezone', 'type' => 'select', 'source_model' => 'Tz\Source']);

        self::assertSame('Berlin', $this->resolver->resolve($field, 'Europe/Berlin'));
    }

    public function testReturnsRawValueAndLogsWhenSourceModelThrows(): void
    {
        $this->sourceFactory->method('create')->willThrowException(new \RuntimeException('Class not found'));
        $this->logger->expects(self::once())->method('debug');

        $field = $this->createField(['id' => 'broken', 'type' => 'select', 'source_model' => 'Broken\Source\Model']);

        self::assertSame('1', $this->resolver->resolve($field, '1'));
    }

    public function testOptionsAreResolvedOncePerFieldPath(): void
    {
        $this->sourceFactory
            ->expects(self::once())
            ->method('create')
            ->willReturn($this->createSourceModel([
                ['value' => '0', 'label' => 'No'],
                ['value' => '1', 'label' => 'Yes'],
            ]));

        $field = $this->createField([
            'id' => 'enabled',
            'type' => 'select',
            'source_model' => 'Magento\Config\Model\Config\Source\Yesno',
        ]);

        self::assertSame('No', $this->resolver->resolve($field, '0'));
        self::assertSame('Yes', $this->resolver->resolve($field, '1'));
    }

    /**
     * @param array<int, array{value: string, label: string}> $options
     */
    private function createFieldWithInlineOptions(array $options, string $type = 'select'): Field
    {
        return $this->createField([
            'id' => 'inline_' . $type,
            'type' => $type,
            'options' => ['option' => $options],
        ]);
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
            $this->sourceFactory,
            $this->createMock(CommentFactory::class),
            $this->createMock(BlockFactory::class),
            $this->createMock(Mapper::class)
        );
        $field->setData($data + ['path' => 'section/group'], 'default');

        return $field;
    }

    /**
     * @param array<int, array<string, mixed>> $options
     */
    private function createSourceModel(array $options): object
    {
        return new class($options) {
            /** @var array<int, array<string, mixed>> */
            private $options;

            /**
             * @param array<int, array<string, mixed>> $options
             */
            public function __construct(array $options)
            {
                $this->options = $options;
            }

            /**
             * @return array<int, array<string, mixed>>
             */
            public function toOptionArray(): array
            {
                return $this->options;
            }
        };
    }
}
