<?php

declare(strict_types=1);

namespace AvS\ScopeHint\Service;

use Magento\Eav\Model\Entity\Attribute\AbstractAttribute;

/**
 * Resolves a raw EAV value into its option label(s). Returns plain, unescaped text;
 * callers escape for their output context.
 */
class EavOptionLabelResolver
{
    /**
     * Source\Table::getOptionText() loads an option collection on every call, and the
     * product grid resolves one value per override row.
     *
     * @var array<string, array<string, string>>
     */
    private $labelsByAttributeId = [];

    public function resolve(AbstractAttribute $attribute, string $value): string
    {
        if ($value === '') {
            return $value;
        }

        $attributeId = (string) $attribute->getAttributeId();

        if ($attributeId !== '' && isset($this->labelsByAttributeId[$attributeId][$value])) {
            return $this->labelsByAttributeId[$attributeId][$value];
        }

        $label = $this->resolveUncached($attribute, $value);

        if ($attributeId !== '') {
            $this->labelsByAttributeId[$attributeId][$value] = $label;
        }

        return $label;
    }

    private function resolveUncached(AbstractAttribute $attribute, string $value): string
    {
        try {
            if (!$attribute->usesSource()) {
                return $value;
            }

            // Table::getOptionText() returns an array of labels for comma-separated
            // multiselect values and false when nothing matches.
            $optionText = $attribute->getSource()->getOptionText($value);
        } catch (\Throwable $e) {
            return $value;
        }

        if (is_array($optionText)) {
            return $optionText !== [] ? implode(', ', array_map('strval', $optionText)) : $value;
        }

        if ($optionText === false || $optionText === null || $optionText === '') {
            return $value;
        }

        return (string) $optionText;
    }
}
