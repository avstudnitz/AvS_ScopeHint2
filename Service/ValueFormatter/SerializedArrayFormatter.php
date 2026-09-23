<?php

declare(strict_types=1);

namespace AvS\ScopeHint\Service\ValueFormatter;

use Magento\Config\Model\Config\Structure\Element\Field;
use Magento\Framework\Escaper;

class SerializedArrayFormatter implements ValueFormatterInterface
{
    /** @var Escaper */
    private $escaper;

    public function __construct(Escaper $escaper)
    {
        $this->escaper = $escaper;
    }

    public function supports(Field $field): bool
    {
        return $this->isSerializedBackend($field);
    }

    /**
     * @param mixed $value
     */
    public function format($value, Field $field): string
    {
        if (is_array($value)) {
            $count = count($value);

            return $this->escaper->escapeHtml((string) __('%1 rows configured', $count));
        }

        if (is_string($value) && $value !== '') {
            try {
                $decoded = json_decode($value, true);
                if (is_array($decoded)) {
                    $count = count($decoded);

                    return $this->escaper->escapeHtml((string) __('%1 rows configured', $count));
                }
            } catch (\Throwable $e) {
                // fall through
            }

            // Legacy PHP serialize format
            try {
                $unserialized = @unserialize($value, ['allowed_classes' => false]);
                if (is_array($unserialized)) {
                    $count = count($unserialized);

                    return $this->escaper->escapeHtml((string) __('%1 rows configured', $count));
                }
            } catch (\Throwable $e) {
                // fall through
            }
        }

        return $this->escaper->escapeHtml((string) __('(serialized data differs)'));
    }

    private function isSerializedBackend(Field $field): bool
    {
        $backendModel = $field->getAttribute('backend_model');

        if (!is_string($backendModel) || $backendModel === '') {
            return false;
        }

        if (strpos($backendModel, 'Backend\\Serialized') !== false) {
            return true;
        }

        if (strpos($backendModel, 'FieldArray\\AbstractFieldArray') !== false) {
            return true;
        }

        return is_subclass_of($backendModel, \Magento\Config\Model\Config\Backend\Serialized::class);
    }
}
