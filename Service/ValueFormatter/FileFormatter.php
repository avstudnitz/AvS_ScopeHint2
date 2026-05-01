<?php

declare(strict_types=1);

namespace AvS\ScopeHint\Service\ValueFormatter;

use Magento\Config\Model\Config\Structure\Element\Field;
use Magento\Framework\Escaper;

class FileFormatter implements ValueFormatterInterface
{
    /** @var Escaper */
    private $escaper;

    public function __construct(Escaper $escaper)
    {
        $this->escaper = $escaper;
    }

    public function supports(Field $field): bool
    {
        $type = $field->getType();

        return $type === 'file' || $type === 'image' || $type === 'imagefile';
    }

    /**
     * @param mixed $value
     */
    public function format($value, Field $field): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return $this->escaper->escapeHtml(basename((string) $value));
    }
}
