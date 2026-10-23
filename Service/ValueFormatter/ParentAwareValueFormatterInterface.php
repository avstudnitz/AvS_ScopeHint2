<?php

declare(strict_types=1);

namespace AvS\ScopeHint\Service\ValueFormatter;

use Magento\Config\Model\Config\Structure\Element\Field;

/**
 * A formatter that can describe an override relative to the value it overrides.
 */
interface ParentAwareValueFormatterInterface extends ValueFormatterInterface
{
    /**
     * @param mixed $value Value saved at the overriding scope
     * @param mixed $parentValue Value inherited from the next higher scope
     */
    public function formatAgainstParent($value, $parentValue, Field $field): string;
}
