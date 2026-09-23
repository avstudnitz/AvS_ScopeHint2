<?php

declare(strict_types=1);

namespace AvS\ScopeHint\Test\Unit\Service;

use AvS\ScopeHint\Service\EavOptionLabelResolver;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute as CatalogAttribute;
use Magento\Eav\Model\Entity\Attribute\Source\AbstractSource;
use PHPUnit\Framework\TestCase;

class EavOptionLabelResolverTest extends TestCase
{
    /** @var EavOptionLabelResolver */
    private $resolver;

    protected function setUp(): void
    {
        $this->resolver = new EavOptionLabelResolver();
    }

    public function testReturnsRawValueForAttributeWithoutSource(): void
    {
        $attribute = $this->createMock(CatalogAttribute::class);
        $attribute->method('usesSource')->willReturn(false);

        self::assertSame('Plain', $this->resolver->resolve($attribute, 'Plain'));
    }

    public function testReturnsSingleLabel(): void
    {
        self::assertSame('Disabled', $this->resolver->resolve($this->createAttributeReturning('Disabled'), '2'));
    }

    public function testJoinsMultiselectLabels(): void
    {
        self::assertSame(
            'Cotton, Wool',
            $this->resolver->resolve($this->createAttributeReturning(['Cotton', 'Wool']), '4,5')
        );
    }

    public function testReturnsRawValueWhenNoOptionMatches(): void
    {
        self::assertSame('99', $this->resolver->resolve($this->createAttributeReturning(false), '99'));
    }

    public function testReturnsRawValueWhenNoMultiselectOptionExists(): void
    {
        self::assertSame('998,999', $this->resolver->resolve($this->createAttributeReturning([]), '998,999'));
    }

    public function testResolvesEachValueOncePerAttribute(): void
    {
        $source = $this->createMock(AbstractSource::class);
        $source->expects(self::exactly(2))->method('getOptionText')->willReturnMap([
            ['2', 'Disabled'],
            ['1', 'Enabled'],
        ]);
        $attribute = $this->createMock(CatalogAttribute::class);
        $attribute->method('getAttributeId')->willReturn('97');
        $attribute->method('usesSource')->willReturn(true);
        $attribute->method('getSource')->willReturn($source);

        foreach (['2', '2', '1', '2', '1'] as $value) {
            $this->resolver->resolve($attribute, $value);
        }

        self::assertSame('Disabled', $this->resolver->resolve($attribute, '2'));
    }

    public function testReturnsRawValueWhenSourceThrows(): void
    {
        $attribute = $this->createMock(CatalogAttribute::class);
        $attribute->method('usesSource')->willReturn(true);
        $attribute->method('getSource')->willThrowException(new \RuntimeException('broken source'));

        self::assertSame('3', $this->resolver->resolve($attribute, '3'));
    }

    /**
     * @param mixed $optionText
     */
    private function createAttributeReturning($optionText): CatalogAttribute
    {
        $source = $this->createMock(AbstractSource::class);
        $source->method('getOptionText')->willReturn($optionText);
        $attribute = $this->createMock(CatalogAttribute::class);
        $attribute->method('usesSource')->willReturn(true);
        $attribute->method('getSource')->willReturn($source);

        return $attribute;
    }
}
