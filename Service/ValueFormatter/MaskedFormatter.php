<?php

declare(strict_types=1);

namespace AvS\ScopeHint\Service\ValueFormatter;

use Magento\Config\Model\Config\Structure\Element\Field;

class MaskedFormatter implements ValueFormatterInterface
{
    public function supports(Field $field): bool
    {
        $type = $field->getType();

        return $type === 'password' || $type === 'obscure';
    }

    /**
     * @param mixed $value
     */
    public function format($value, Field $field): string
    {
        if ($value === null || $value === '' || $value === []) {
            return '';
        }

        return '●●●●●●';
    }
}
