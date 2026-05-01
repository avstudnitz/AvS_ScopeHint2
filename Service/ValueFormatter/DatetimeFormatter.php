<?php

declare(strict_types=1);

namespace AvS\ScopeHint\Service\ValueFormatter;

use Magento\Config\Model\Config\Structure\Element\Field;
use Magento\Framework\Escaper;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;

class DatetimeFormatter implements ValueFormatterInterface
{
    /** @var TimezoneInterface */
    private $timezone;

    /** @var Escaper */
    private $escaper;

    public function __construct(
        TimezoneInterface $timezone,
        Escaper $escaper
    ) {
        $this->timezone = $timezone;
        $this->escaper = $escaper;
    }

    public function supports(Field $field): bool
    {
        $type = $field->getType();

        return $type === 'datetime' || $type === 'date' || $type === 'time';
    }

    /**
     * @param mixed $value
     */
    public function format($value, Field $field): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        try {
            $date = $this->timezone->date(new \DateTime((string) $value));
            $formatted = $this->timezone->formatDate($date);

            return $this->escaper->escapeHtml($formatted);
        } catch (\Throwable $e) {
            return $this->escaper->escapeHtml((string) $value);
        }
    }
}
