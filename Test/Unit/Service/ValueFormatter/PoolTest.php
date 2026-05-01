<?php

declare(strict_types=1);

namespace AvS\ScopeHint\Test\Unit\Service\ValueFormatter;

use AvS\ScopeHint\Service\ValueFormatter\DefaultFormatter;
use AvS\ScopeHint\Service\ValueFormatter\MaskedFormatter;
use AvS\ScopeHint\Service\ValueFormatter\Pool;
use AvS\ScopeHint\Service\ValueFormatter\ValueFormatterInterface;
use Magento\Config\Model\Config\Structure\Element\Field;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PoolTest extends TestCase
{
    public function testResolveReturnsFirstSupportingFormatter(): void
    {
        $field = $this->createMock(Field::class);
        $field->method('getType')->willReturn('password');

        /** @var ValueFormatterInterface&MockObject $rejectingFormatter */
        $rejectingFormatter = $this->createMock(ValueFormatterInterface::class);
        $rejectingFormatter->method('supports')->willReturn(false);

        $maskedFormatter = new MaskedFormatter();

        $pool = new Pool([$rejectingFormatter, $maskedFormatter]);

        $resolved = $pool->resolve($field);

        self::assertInstanceOf(MaskedFormatter::class, $resolved);
    }

    public function testResolveSkipsNonMatchingFormatters(): void
    {
        $field = $this->createMock(Field::class);
        $field->method('getType')->willReturn('password');

        /** @var ValueFormatterInterface&MockObject $notMatching */
        $notMatching = $this->createMock(ValueFormatterInterface::class);
        $notMatching->method('supports')->willReturn(false);

        $maskedFormatter = new MaskedFormatter();

        $pool = new Pool([$notMatching, $maskedFormatter]);

        $resolved = $pool->resolve($field);

        self::assertSame($maskedFormatter, $resolved);
    }

    public function testResolveThrowsWhenNoFormatterRegistered(): void
    {
        $this->expectException(\LogicException::class);

        $field = $this->createMock(Field::class);

        /** @var ValueFormatterInterface&MockObject $notMatching */
        $notMatching = $this->createMock(ValueFormatterInterface::class);
        $notMatching->method('supports')->willReturn(false);

        $pool = new Pool([$notMatching]);
        $pool->resolve($field);
    }

    public function testDefaultFormatterServesAsFallback(): void
    {
        $field = $this->createMock(Field::class);
        $field->method('getType')->willReturn('text');

        /** @var DefaultFormatter&MockObject $defaultFormatter */
        $defaultFormatter = $this->createMock(DefaultFormatter::class);
        $defaultFormatter->method('supports')->willReturn(true);

        $pool = new Pool([$defaultFormatter]);

        $resolved = $pool->resolve($field);

        self::assertSame($defaultFormatter, $resolved);
    }
}
