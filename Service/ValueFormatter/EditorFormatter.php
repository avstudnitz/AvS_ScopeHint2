<?php

declare(strict_types=1);

namespace AvS\ScopeHint\Service\ValueFormatter;

use Magento\Config\Model\Config\Structure\Element\Field;
use Magento\Framework\Escaper;
use Magento\Framework\Filter\StripTags;

class EditorFormatter implements ValueFormatterInterface
{
    private const MAX_LENGTH = 80;

    /** @var StripTags */
    private $stripTags;

    /** @var Escaper */
    private $escaper;

    public function __construct(
        StripTags $stripTags,
        Escaper $escaper
    ) {
        $this->stripTags = $stripTags;
        $this->escaper = $escaper;
    }

    public function supports(Field $field): bool
    {
        if ($field->getType() === 'editor') {
            return true;
        }

        $frontendModel = (string) $field->getFrontendModel();

        return $frontendModel !== '' && stripos($frontendModel, 'Pagebuilder') !== false;
    }

    /**
     * @param mixed $value
     */
    public function format($value, Field $field): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $stripped = $this->stripTags->filter((string) $value);
        $stripped = trim($stripped);

        if (mb_strlen($stripped) > self::MAX_LENGTH) {
            $stripped = mb_substr($stripped, 0, self::MAX_LENGTH) . '...';
        }

        $label = $stripped . ' ' . (string) __('(HTML content differs)');

        return $this->escaper->escapeHtml($label);
    }
}
