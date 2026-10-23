<?php

declare(strict_types=1);

namespace AvS\ScopeHint\Service;

use Magento\Config\Model\Config\Structure\Element\Field as Subject;
use Psr\Log\LoggerInterface;

/**
 * Resolves a raw config value into its human-readable label for select-style fields.
 *
 * For multiselect fields a comma-separated value is split and each part resolved
 * individually. For non-select fields the value is returned unchanged.
 *
 * Options come from Field::getOptions(), which covers both inline <options> and
 * source_model (instantiated by core through Config\SourceFactory).
 */
class OptionLabelResolver
{
    /** @var LoggerInterface */
    private $logger;

    /**
     * Keyed by field path: Field instances are flyweights re-populated per field,
     * so the object itself cannot be the cache key.
     *
     * @var array<string, array<int|string, mixed>>
     */
    private $optionsByFieldPath = [];

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @param mixed $value
     */
    public function resolve(Subject $field, $value): string
    {
        if (!is_string($value) && !is_int($value) && !is_float($value)) {
            return (string) $value;
        }

        $value = trim((string) $value);

        if ($field->getType() === 'multiselect' && strpos($value, ',') !== false) {
            $parts = array_map(
                function ($part) use ($field) {
                    return $this->resolve($field, $part);
                },
                explode(',', $value)
            );

            return implode(', ', $parts);
        }

        if (!$field->hasOptions()) {
            return $value;
        }

        return $this->matchOption($this->getOptions($field), $value);
    }

    /**
     * @param array<int|string, mixed> $options
     */
    private function matchOption(array $options, string $value): string
    {
        foreach ($options as $option) {
            if (!is_array($option) || !array_key_exists('value', $option)) {
                continue;
            }

            if (is_array($option['value'])) {
                $label = $this->matchOption($option['value'], $value);
                if ($label !== $value) {
                    return $label;
                }
                continue;
            }

            if ((string) $option['value'] === $value) {
                return (string) ($option['label'] ?? $value);
            }
        }

        return $value;
    }

    /**
     * @return array<int|string, mixed>
     */
    private function getOptions(Subject $field): array
    {
        $fieldPath = (string) $field->getPath();

        if (array_key_exists($fieldPath, $this->optionsByFieldPath)) {
            return $this->optionsByFieldPath[$fieldPath];
        }

        try {
            $options = $field->getOptions();
        } catch (\Throwable $e) {
            $this->logger->debug(
                sprintf(
                    'AvS_ScopeHint2: failed to resolve options for config field "%s": %s',
                    $fieldPath,
                    $e->getMessage()
                )
            );
            $options = [];
        }

        $this->optionsByFieldPath[$fieldPath] = is_array($options) ? $options : [];

        return $this->optionsByFieldPath[$fieldPath];
    }
}
