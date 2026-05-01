<?php

declare(strict_types=1);

namespace AvS\ScopeHint\Service;

use Magento\Config\Model\Config\Structure\Element\Field as Subject;

/**
 * Resolves a raw config value into its human-readable label for select-style fields.
 *
 * For multiselect fields a comma-separated value is split and each part resolved
 * individually. For non-select fields the value is returned unchanged.
 */
class OptionLabelResolver
{
    /**
     * @param mixed $value
     */
    public function resolve(Subject $field, $value): string
    {
        if (!is_string($value) && !is_int($value) && !is_float($value)) {
            return (string) $value;
        }

        $value = trim((string) $value);

        if (!$field->hasOptions()) {
            return $value;
        }

        if ($field->getType() === 'multiselect' && strpos($value, ',') !== false) {
            $parts = array_map(
                function ($part) use ($field) {
                    return $this->resolve($field, $part);
                },
                explode(',', $value)
            );
            return implode(', ', $parts);
        }

        foreach ($field->getOptions() as $option) {
            if (is_array($option) && isset($option['value']) && (string) $option['value'] === $value) {
                return (string) ($option['label'] ?? $value);
            }
        }

        return $value;
    }
}
