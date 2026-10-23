<?php

declare(strict_types=1);

namespace AvS\ScopeHint\Test\Unit\Service\ValueFormatter;

use AvS\ScopeHint\Service\ValueFormatter\DatetimeFormatter;
use Magento\Config\Model\Config\Structure\Element\Field;
use Magento\Framework\Escaper;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class DatetimeFormatterTest extends TestCase
{
    /** @var TimezoneInterface&MockObject */
    private $timezone;

    /** @var Escaper&MockObject */
    private $escaper;

    /** @var DatetimeFormatter */
    private $formatter;

    protected function setUp(): void
    {
        $this->timezone = $this->createMock(TimezoneInterface::class);
        $this->escaper = $this->createMock(Escaper::class);
        $this->escaper->method('escapeHtml')->willReturnArgument(0);

        $this->formatter = new DatetimeFormatter($this->timezone, $this->escaper);
    }

    public function testSupportsDatetimeType(): void
    {
        $field = $this->createMock(Field::class);
        $field->method('getType')->willReturn('datetime');

        self::assertTrue($this->formatter->supports($field));
    }

    public function testSupportsDateType(): void
    {
        $field = $this->createMock(Field::class);
        $field->method('getType')->willReturn('date');

        self::assertTrue($this->formatter->supports($field));
    }

    public function testSupportsTimeType(): void
    {
        $field = $this->createMock(Field::class);
        $field->method('getType')->willReturn('time');

        self::assertTrue($this->formatter->supports($field));
    }

    public function testDoesNotSupportTextType(): void
    {
        $field = $this->createMock(Field::class);
        $field->method('getType')->willReturn('text');

        self::assertFalse($this->formatter->supports($field));
    }

    public function testFormatReturnsFormattedDate(): void
    {
        $field = $this->createMock(Field::class);
        $field->method('getType')->willReturn('date');
        $dateObj = new \DateTime('2024-01-15 10:00:00');

        $this->timezone->method('date')->willReturn($dateObj);
        $this->timezone->method('formatDate')
            ->with($dateObj, \IntlDateFormatter::SHORT, false)
            ->willReturn('Jan 15, 2024');

        $result = $this->formatter->format('2024-01-15 10:00:00', $field);

        self::assertSame('Jan 15, 2024', $result);
    }

    public function testFormatDatetimeIncludesTime(): void
    {
        $field = $this->createMock(Field::class);
        $field->method('getType')->willReturn('datetime');
        $dateObj = new \DateTime('2024-01-15 10:00:00');

        $this->timezone->method('date')->willReturn($dateObj);
        $this->timezone->expects(self::once())
            ->method('formatDate')
            ->with($dateObj, \IntlDateFormatter::SHORT, true)
            ->willReturn('1/15/24, 10:00 AM');

        self::assertSame('1/15/24, 10:00 AM', $this->formatter->format('2024-01-15 10:00:00', $field));
    }

    public function testFormatTimeOmitsDate(): void
    {
        $field = $this->createMock(Field::class);
        $field->method('getType')->willReturn('time');
        $dateObj = new \DateTime('2024-01-15 10:00:00');

        $this->timezone->method('date')->willReturn($dateObj);
        $this->timezone->expects(self::once())
            ->method('formatDateTime')
            ->with($dateObj, \IntlDateFormatter::NONE, \IntlDateFormatter::SHORT)
            ->willReturn('10:00 AM');

        self::assertSame('10:00 AM', $this->formatter->format('10:00:00', $field));
    }

    public function testFormatEmptyValueReturnsEmptyString(): void
    {
        $field = $this->createMock(Field::class);

        self::assertSame('', $this->formatter->format('', $field));
        self::assertSame('', $this->formatter->format(null, $field));
    }

    public function testFormatInvalidDateFallsBackToRawValue(): void
    {
        $field = $this->createMock(Field::class);

        $this->timezone->method('date')->willThrowException(new \Exception('invalid date'));

        $result = $this->formatter->format('not-a-date', $field);

        self::assertSame('not-a-date', $result);
    }
}
