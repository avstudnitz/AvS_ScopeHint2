<?php

declare(strict_types=1);

namespace AvS\ScopeHint\Test\Unit\Service\ValueFormatter;

use AvS\ScopeHint\Service\ValueFormatter\SerializedArrayFormatter;
use Magento\Config\Model\Config\Structure\Element\Field;
use Magento\Framework\Escaper;
use PHPUnit\Framework\TestCase;

class SerializedArrayFormatterGapTest extends TestCase
{
    /** @var SerializedArrayFormatter */
    private $formatter;

    /** @var Field */
    private $field;

    protected function setUp(): void
    {
        $escaper = $this->createMock(Escaper::class);
        $escaper->method('escapeHtml')->willReturnCallback(
            static function ($value) {
                return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
            }
        );
        $this->formatter = new SerializedArrayFormatter($escaper);
        $this->field = $this->createMock(Field::class);
    }

    public function testLegacyPhpSerializedParentIsDiffed(): void
    {
        $parent = serialize([['title' => 'A']]);

        self::assertSame(
            '1 row; row 1: title “B”',
            $this->formatter->formatAgainstParent([['title' => 'B']], $parent, $this->field)
        );
    }

    public function testLegacyPhpSerializedValueIsDiffed(): void
    {
        $value = serialize([['title' => 'B']]);

        self::assertSame(
            '1 row; row 1: title “B”',
            $this->formatter->formatAgainstParent($value, [['title' => 'A']], $this->field)
        );
    }

    public function testSerializedObjectIsNotInstantiatedAndFallsBack(): void
    {
        $parent = serialize(new \stdClass());

        self::assertSame(
            '(serialized data differs)',
            $this->formatter->formatAgainstParent('not decodable', $parent, $this->field)
        );
    }

    public function testRowsThatAreNotArraysAreComparedAsText(): void
    {
        self::assertSame(
            '2 rows; row 2: “c”',
            $this->formatter->formatAgainstParent(['a', 'c'], ['a', 'b'], $this->field)
        );
    }

    public function testScalarRowAgainstArrayRowIsReportedAsChange(): void
    {
        $result = $this->formatter->formatAgainstParent(['plain'], [['title' => 'A']], $this->field);

        self::assertStringStartsWith('1 row; row 1: “', $result);
    }

    public function testColumnMissingInValueButPresentInParentIsReportedAsRemoved(): void
    {
        $parent = [['title' => 'A', 'code' => 'a']];
        $value = [['title' => 'A']];

        self::assertSame(
            '1 row; row 1: code removed',
            $this->formatter->formatAgainstParent($value, $parent, $this->field)
        );
    }

    public function testColumnPresentInValueButMissingInParentIsReported(): void
    {
        $parent = [['title' => 'A']];
        $value = [['title' => 'A', 'code' => 'x']];

        self::assertSame(
            '1 row; row 1: code “x”',
            $this->formatter->formatAgainstParent($value, $parent, $this->field)
        );
    }

    public function testMultibyteCellIsTruncatedByCharacterNotByte(): void
    {
        $longText = str_repeat('ä', 45);

        $result = $this->formatter->formatAgainstParent(
            [['title' => $longText]],
            [['title' => 'x']],
            $this->field
        );

        self::assertSame('1 row; row 1: title “' . str_repeat('ä', 40) . '...”', $result);
    }

    public function testCellOfExactlyFortyCharactersIsNotTruncated(): void
    {
        $text = str_repeat('ü', 40);

        $result = $this->formatter->formatAgainstParent([['title' => $text]], [['title' => 'x']], $this->field);

        self::assertSame('1 row; row 1: title “' . $text . '”', $result);
    }

    public function testNestedNonScalarCellChangeIsDetectedAndRendered(): void
    {
        $result = $this->formatter->formatAgainstParent(
            [['options' => ['a' => 2]]],
            [['options' => ['a' => 1]]],
            $this->field
        );

        self::assertSame('1 row; row 1: options “{&quot;a&quot;:2}”', $result);
    }

    public function testIdenticalNestedCellsAreReportedAsSameAsParent(): void
    {
        $rows = [['options' => ['a' => 1]]];

        self::assertSame(
            '1 row, same as parent scope',
            $this->formatter->formatAgainstParent($rows, $rows, $this->field)
        );
    }

    public function testEmptyValueAgainstRowsReportsAllRowsRemoved(): void
    {
        self::assertSame(
            '0 rows; row 1 removed',
            $this->formatter->formatAgainstParent([], [['title' => 'A']], $this->field)
        );
    }
}
