<?php

declare(strict_types=1);

namespace AvS\ScopeHint\Test\Unit\Service;

use AvS\ScopeHint\Api\Data\OverrideResultInterface;
use AvS\ScopeHint\Api\OverrideSourceInterface;
use AvS\ScopeHint\Service\OverrideSourcePool;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class OverrideSourcePoolTest extends TestCase
{
    private function makeSource(bool $supports, ?OverrideResultInterface $result = null): OverrideSourceInterface
    {
        /** @var OverrideSourceInterface&MockObject $source */
        $source = $this->createMock(OverrideSourceInterface::class);
        $source->method('supports')->willReturn($supports);

        if ($result !== null) {
            $source->method('getOverrides')->willReturn($result);
        }

        return $source;
    }

    public function testReturnsNullWhenNoSourcesRegistered(): void
    {
        $pool = new OverrideSourcePool([]);

        $result = $pool->resolve('product', 1, ['code' => 'name']);

        self::assertNull($result);
    }

    public function testReturnsNullWhenNoSourceMatches(): void
    {
        $pool = new OverrideSourcePool([
            $this->makeSource(false),
            $this->makeSource(false),
        ]);

        $result = $pool->resolve('product', 1, ['code' => 'name']);

        self::assertNull($result);
    }

    public function testFirstSupportingSourceWins(): void
    {
        $expected = $this->createMock(OverrideResultInterface::class);

        $first  = $this->makeSource(false);
        $second = $this->makeSource(true, $expected);
        $third  = $this->makeSource(true);

        // Third source supports() should never be called because second wins.
        $third->expects(self::never())->method('getOverrides');

        $pool = new OverrideSourcePool([$first, $second, $third]);

        $result = $pool->resolve('product', 42, ['code' => 'sku']);

        self::assertSame($expected, $result);
    }

    public function testSupportIsCalledWithCorrectArguments(): void
    {
        $fieldMeta  = ['code' => 'description', 'globalScope' => false];
        $entityType = 'product';
        $entityId   = 7;

        /** @var OverrideSourceInterface&MockObject $source */
        $source = $this->createMock(OverrideSourceInterface::class);
        $source->expects(self::once())
            ->method('supports')
            ->with($entityType, $fieldMeta)
            ->willReturn(false);

        $pool = new OverrideSourcePool([$source]);
        $pool->resolve($entityType, $entityId, $fieldMeta);
    }

    public function testGetOverridesIsCalledWithCorrectArguments(): void
    {
        $fieldMeta  = ['code' => 'name'];
        $entityType = 'category';
        $entityId   = 99;

        $expected = $this->createMock(OverrideResultInterface::class);

        /** @var OverrideSourceInterface&MockObject $source */
        $source = $this->createMock(OverrideSourceInterface::class);
        $source->method('supports')->willReturn(true);
        $source->expects(self::once())
            ->method('getOverrides')
            ->with($entityType, $entityId, $fieldMeta)
            ->willReturn($expected);

        $pool = new OverrideSourcePool([$source]);
        $result = $pool->resolve($entityType, $entityId, $fieldMeta);

        self::assertSame($expected, $result);
    }

    public function testSubsequentSourcesDoNotFireAfterFirstMatch(): void
    {
        $expected = $this->createMock(OverrideResultInterface::class);

        /** @var OverrideSourceInterface&MockObject $first */
        $first = $this->createMock(OverrideSourceInterface::class);
        $first->method('supports')->willReturn(true);
        $first->method('getOverrides')->willReturn($expected);

        /** @var OverrideSourceInterface&MockObject $second */
        $second = $this->createMock(OverrideSourceInterface::class);
        $second->expects(self::never())->method('supports');
        $second->expects(self::never())->method('getOverrides');

        $pool = new OverrideSourcePool([$first, $second]);
        $pool->resolve('product', 1, ['code' => 'price']);
    }
}
