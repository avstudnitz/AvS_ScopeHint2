<?php

declare(strict_types=1);

namespace AvS\ScopeHint\Test\Unit\Service\ValueFormatter;

use AvS\ScopeHint\Service\ValueFormatter\SerializedArrayFormatter;
use Magento\Config\Model\Config\Structure\Element\Field;
use Magento\Framework\Escaper;
use PHPUnit\Framework\TestCase;

class SerializedArrayFormatterBlindTest extends TestCase
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

    private function twoRows(string $secondTitle = 'Payment'): array
    {
        return [
            'r1' => ['title' => 'Shipping', 'code' => 'ship'],
            'r2' => ['title' => $secondTitle, 'code' => 'pay'],
        ];
    }

    public function testC6_1RowTwoTitleDiffers(): void
    {
        $result = $this->formatter->formatAgainstParent(
            $this->twoRows('Zahlung'),
            $this->twoRows(),
            $this->field
        );

        self::assertSame('2 rows; row 2: title “Zahlung”', $result);
    }

    public function testC6_2TwoColumnsDifferInOneRow(): void
    {
        $parent = [['title' => 'A', 'code' => 'a']];
        $value = [['title' => 'X', 'code' => 'x']];

        self::assertSame(
            '1 row; row 1: title “X”, code “x”',
            $this->formatter->formatAgainstParent($value, $parent, $this->field)
        );
    }

    public function testC6_3ExtraRowAdded(): void
    {
        $parent = $this->twoRows();
        $value = $this->twoRows();
        $value['r3'] = ['title' => 'Extra', 'code' => 'extra'];

        self::assertSame(
            '3 rows; row 3 added',
            $this->formatter->formatAgainstParent($value, $parent, $this->field)
        );
    }

    public function testC6_4RowRemoved(): void
    {
        $parent = $this->twoRows();
        $value = ['r1' => ['title' => 'Shipping', 'code' => 'ship']];

        self::assertSame(
            '1 row; row 2 removed',
            $this->formatter->formatAgainstParent($value, $parent, $this->field)
        );
    }

    public function testC6_5RowsComparedByPositionNotKey(): void
    {
        $parent = [
            '_1700000000_1' => ['title' => 'Shipping', 'code' => 'ship'],
            '_1700000000_2' => ['title' => 'Payment', 'code' => 'pay'],
        ];
        $value = [
            '_1800000000_1' => ['title' => 'Shipping', 'code' => 'ship'],
            '_1800000000_2' => ['title' => 'Bezahlung', 'code' => 'pay'],
        ];

        self::assertSame(
            '2 rows; row 2: title “Bezahlung”',
            $this->formatter->formatAgainstParent($value, $parent, $this->field)
        );
    }

    public function testC6_6OnlyRowKeysDiffer(): void
    {
        $parent = [
            '_1700000000_1' => ['title' => 'Shipping', 'code' => 'ship'],
            '_1700000000_2' => ['title' => 'Payment', 'code' => 'pay'],
        ];
        $value = [
            '_1800000000_1' => ['title' => 'Shipping', 'code' => 'ship'],
            '_1800000000_2' => ['title' => 'Payment', 'code' => 'pay'],
        ];

        self::assertSame(
            '2 rows, same as parent scope',
            $this->formatter->formatAgainstParent($value, $parent, $this->field)
        );
    }

    public function testC6_7FiveChangedRowsAreTruncatedToThree(): void
    {
        $parent = [];
        $value = [];
        foreach (['a', 'b', 'c', 'd', 'e'] as $index => $letter) {
            $parent['p' . $index] = ['title' => 'old' . $letter];
            $value['v' . $index] = ['title' => $letter];
        }

        self::assertSame(
            '5 rows; row 1: title “a”; row 2: title “b”; row 3: title “c”; +2 more',
            $this->formatter->formatAgainstParent($value, $parent, $this->field)
        );
    }

    public function testC6_8LongColumnValueIsTruncatedTo40Characters(): void
    {
        $longText = str_repeat('abcdefghij', 5);
        $parent = [['title' => 'short']];
        $value = [['title' => $longText]];

        $result = $this->formatter->formatAgainstParent($value, $parent, $this->field);

        self::assertStringContainsString(substr($longText, 0, 40) . '...', $result);
        self::assertStringNotContainsString(substr($longText, 0, 41), $result);
    }

    public function testC6_9ParentNotUsableFallsBackToRowCount(): void
    {
        $value = $this->twoRows();

        foreach ([null, '', 'not json at all'] as $parent) {
            self::assertSame(
                '2 rows configured',
                $this->formatter->formatAgainstParent($value, $parent, $this->field)
            );
        }
    }

    public function testC6_9SingleRowKeepsExistingWording(): void
    {
        $value = [['title' => 'Only']];

        self::assertSame(
            '1 rows configured',
            $this->formatter->formatAgainstParent($value, null, $this->field)
        );
    }

    public function testC6_10JsonAndArrayInputsGiveSameResult(): void
    {
        $value = $this->twoRows('Zahlung');
        $parent = $this->twoRows();

        $expected = $this->formatter->formatAgainstParent($value, $parent, $this->field);

        self::assertSame(
            $expected,
            $this->formatter->formatAgainstParent(json_encode($value), $parent, $this->field)
        );
        self::assertSame(
            $expected,
            $this->formatter->formatAgainstParent($value, json_encode($parent), $this->field)
        );
        self::assertSame(
            $expected,
            $this->formatter->formatAgainstParent(json_encode($value), json_encode($parent), $this->field)
        );
        self::assertSame('2 rows; row 2: title “Zahlung”', $expected);
    }

    public function testC6_11ColumnValueIsHtmlEscaped(): void
    {
        $parent = [['title' => 'plain']];
        $value = [['title' => '<b>x</b>']];

        $result = $this->formatter->formatAgainstParent($value, $parent, $this->field);

        self::assertStringContainsString('&lt;b&gt;x&lt;/b&gt;', $result);
        self::assertStringNotContainsString('<b>', $result);
    }

    public function testC6_12FormatIsUnchanged(): void
    {
        $rows = [];
        for ($i = 1; $i <= 5; $i++) {
            $rows['row' . $i] = ['title' => 'T' . $i];
        }

        self::assertSame('5 rows configured', $this->formatter->format($rows, $this->field));
    }
}
