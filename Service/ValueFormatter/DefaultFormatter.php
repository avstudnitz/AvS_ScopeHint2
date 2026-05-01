<?php

declare(strict_types=1);

namespace AvS\ScopeHint\Service\ValueFormatter;

use AvS\ScopeHint\Service\OptionLabelResolver;
use Magento\Config\Model\Config\Structure\Element\Field;
use Magento\Framework\Escaper;

class DefaultFormatter implements ValueFormatterInterface
{
    /** @var OptionLabelResolver */
    private $labelResolver;

    /** @var Escaper */
    private $escaper;

    public function __construct(
        OptionLabelResolver $labelResolver,
        Escaper $escaper
    ) {
        $this->labelResolver = $labelResolver;
        $this->escaper = $escaper;
    }

    public function supports(Field $field): bool
    {
        return true;
    }

    /**
     * @param mixed $value
     */
    public function format($value, Field $field): string
    {
        if (is_array($value)) {
            return $this->escaper->escapeHtml((string) __('(complex value)'));
        }

        $resolved = $this->labelResolver->resolve($field, (string) $value);

        return $this->escaper->escapeHtml($resolved);
    }
}
