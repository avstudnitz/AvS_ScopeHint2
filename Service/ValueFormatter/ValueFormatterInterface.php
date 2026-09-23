<?php

declare(strict_types=1);

namespace AvS\ScopeHint\Service\ValueFormatter;

use Magento\Config\Model\Config\Structure\Element\Field;

interface ValueFormatterInterface
{
    public function supports(Field $field): bool;

    /**
     * @param mixed $value
     */
    public function format($value, Field $field): string;
}
