<?php

declare(strict_types=1);

namespace AvS\ScopeHint\Test\Unit\Service;

use AvS\ScopeHint\Service\OptionLabelResolver;
use Magento\Config\Model\Config\Structure\Element\Field;
use Magento\Framework\ObjectManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class OptionLabelResolverTest extends TestCase
{
    /** @var ObjectManagerInterface&MockObject */
    private $objectManager;

    /** @var LoggerInterface&MockObject */
    private $logger;

    /** @var OptionLabelResolver */
    private $resolver;

    protected function setUp(): void
    {
        $this->objectManager = $this->createMock(ObjectManagerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->resolver = new OptionLabelResolver($this->objectManager, $this->logger);
    }

    // -------------------------------------------------------------------------
    // Inline options (existing behaviour)
    // -------------------------------------------------------------------------

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
        $field = $this->createMock(Field::class);
        $field->method('hasOptions')->willReturn(false);
        $field->method('getSourceModel')->willReturn(null);
        $field->method('getType')->willReturn('text');

        self::assertSame('some text', $this->resolver->resolve($field, 'some text'));
    }

    // -------------------------------------------------------------------------
    // Source-model resolution
    // -------------------------------------------------------------------------

    public function testResolvesLabelFromSourceModel(): void
    {
        $sourceModel = $this->createSourceModel([
            ['value' => '0', 'label' => 'No'],
            ['value' => '1', 'label' => 'Yes'],
        ]);

        $this->objectManager
            ->expects(self::once())
            ->method('create')
            ->with('Magento\Config\Model\Config\Source\Yesno')
            ->willReturn($sourceModel);

        $field = $this->createFieldWithSourceModel('Magento\Config\Model\Config\Source\Yesno');

        self::assertSame('No', $this->resolver->resolve($field, '0'));
    }

    public function testReturnsRawValueWhenSourceModelThrows(): void
    {
        $this->objectManager
            ->expects(self::once())
            ->method('create')
            ->willThrowException(new \RuntimeException('Class not found'));

        $this->logger
            ->expects(self::once())
            ->method('debug');

        $field = $this->createFieldWithSourceModel('Broken\Source\Model');

        self::assertSame('1', $this->resolver->resolve($field, '1'));
    }

    public function testSourceModelExceptionOnToOptionArrayReturnsRawValue(): void
    {
        $sourceModel = new class {
            public function toOptionArray(): array
            {
                throw new \RuntimeException('toOptionArray failed');
            }
        };

        $this->objectManager
            ->expects(self::once())
            ->method('create')
            ->willReturn($sourceModel);

        $this->logger
            ->expects(self::once())
            ->method('debug');

        $field = $this->createFieldWithSourceModel('Some\Source\Model');

        self::assertSame('42', $this->resolver->resolve($field, '42'));
    }

    // -------------------------------------------------------------------------
    // Cache: same FQCN → object-manager create() called only once
    // -------------------------------------------------------------------------

    public function testSourceModelOptionsAreCachedPerFqcn(): void
    {
        $sourceModel = $this->createSourceModel([
            ['value' => '0', 'label' => 'No'],
            ['value' => '1', 'label' => 'Yes'],
        ]);

        $this->objectManager
            ->expects(self::once()) // must be called exactly once despite two resolve() calls
            ->method('create')
            ->with('Magento\Config\Model\Config\Source\Yesno')
            ->willReturn($sourceModel);

        $field = $this->createFieldWithSourceModel('Magento\Config\Model\Config\Source\Yesno');

        $first = $this->resolver->resolve($field, '0');
        $second = $this->resolver->resolve($field, '1');

        self::assertSame('No', $first);
        self::assertSame('Yes', $second);
    }

    public function testFailedSourceModelIsCachedToAvoidRetry(): void
    {
        $this->objectManager
            ->expects(self::once()) // only one create attempt even with two resolve() calls
            ->method('create')
            ->willThrowException(new \RuntimeException('fail'));

        $this->logger->method('debug');

        $field = $this->createFieldWithSourceModel('Broken\Source\Model');

        $this->resolver->resolve($field, '0');
        $this->resolver->resolve($field, '1');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * @param array<int, array{value: string, label: string}> $options
     */
    private function createFieldWithInlineOptions(array $options, string $type = 'select'): Field
    {
        $field = $this->createMock(Field::class);
        $field->method('hasOptions')->willReturn(true);
        $field->method('getOptions')->willReturn($options);
        $field->method('getType')->willReturn($type);
        return $field;
    }

    private function createFieldWithSourceModel(string $sourceModelFqcn): Field
    {
        $field = $this->createMock(Field::class);
        $field->method('hasOptions')->willReturn(false);
        $field->method('getSourceModel')->willReturn($sourceModelFqcn);
        $field->method('getType')->willReturn('select');
        return $field;
    }

    /**
     * @param array<int, array{value: string, label: string}> $options
     * @return object
     */
    private function createSourceModel(array $options): object
    {
        return new class($options) {
            /** @var array<int, array{value: string, label: string}> */
            private $options;

            /**
             * @param array<int, array{value: string, label: string}> $options
             */
            public function __construct(array $options)
            {
                $this->options = $options;
            }

            /**
             * @return array<int, array{value: string, label: string}>
             */
            public function toOptionArray(): array
            {
                return $this->options;
            }
        };
    }
}
