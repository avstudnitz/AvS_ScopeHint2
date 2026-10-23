<?php

declare(strict_types=1);

namespace AvS\ScopeHint\Service\ValueFormatter;

use Magento\Config\Model\Config\Structure\Element\Field;

class Pool
{
    /** @var ValueFormatterInterface[] */
    private $formatters;

    /**
     * @param ValueFormatterInterface[] $formatters
     */
    public function __construct(array $formatters = [])
    {
        $this->formatters = $formatters;
    }

    public function resolve(Field $field): ValueFormatterInterface
    {
        foreach ($this->formatters as $formatter) {
            if ($formatter->supports($field)) {
                return $formatter;
            }
        }

        // Should never be reached if DefaultFormatter is registered last.
        // Kept as a safety net.
        foreach ($this->formatters as $formatter) {
            if ($formatter instanceof DefaultFormatter) {
                return $formatter;
            }
        }

        throw new \LogicException('No formatter found and DefaultFormatter is not registered in pool.');
    }
}
