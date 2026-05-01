<?php

declare(strict_types=1);

namespace AvS\ScopeHint\Service;

use Magento\Config\Model\Config\Structure\Element\Field as Subject;
use Magento\Framework\ObjectManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Resolves a raw config value into its human-readable label for select-style fields.
 *
 * For multiselect fields a comma-separated value is split and each part resolved
 * individually. For non-select fields the value is returned unchanged.
 *
 * Resolution order:
 * 1. Inline options from system.xml (<options> element)
 * 2. Source model (source_model attribute on the field)
 * 3. Raw value unchanged
 */
class OptionLabelResolver
{
    /** @var ObjectManagerInterface */
    private $objectManager;

    /** @var LoggerInterface */
    private $logger;

    /**
     * Cache of resolved option arrays keyed by source-model FQCN.
     *
     * @var array<string, array>
     */
    private $sourceModelOptionsCache = [];

    public function __construct(
        ObjectManagerInterface $objectManager,
        LoggerInterface $logger
    ) {
        $this->objectManager = $objectManager;
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

        if ($field->hasOptions()) {
            return $this->matchOption($field->getOptions(), $value);
        }

        $sourceModel = $field->getSourceModel();
        if ($sourceModel !== null && $sourceModel !== '') {
            $options = $this->getSourceModelOptions((string) $sourceModel);
            if ($options !== null) {
                return $this->matchOption($options, $value);
            }
        }

        return $value;
    }

    /**
     * @param array<int|string, mixed> $options
     */
    private function matchOption(array $options, string $value): string
    {
        foreach ($options as $option) {
            if (is_array($option) && isset($option['value']) && (string) $option['value'] === $value) {
                return (string) ($option['label'] ?? $value);
            }
        }

        return $value;
    }

    /**
     * Returns the option array for the given source-model class, using a per-request cache.
     * Returns null if instantiation or toOptionArray() fails.
     *
     * @return array<int|string, mixed>|null
     */
    private function getSourceModelOptions(string $sourceModelClass): ?array
    {
        if (array_key_exists($sourceModelClass, $this->sourceModelOptionsCache)) {
            return $this->sourceModelOptionsCache[$sourceModelClass];
        }

        try {
            $sourceModel = $this->objectManager->create($sourceModelClass);
            $options = $sourceModel->toOptionArray();

            if (!is_array($options)) {
                $this->sourceModelOptionsCache[$sourceModelClass] = null;
                return null;
            }

            $this->sourceModelOptionsCache[$sourceModelClass] = $options;
            return $options;
        } catch (\Throwable $e) {
            $this->logger->debug(
                sprintf(
                    'AvS_ScopeHint2: failed to resolve options from source model "%s": %s',
                    $sourceModelClass,
                    $e->getMessage()
                )
            );
            $this->sourceModelOptionsCache[$sourceModelClass] = null;
            return null;
        }
    }
}
