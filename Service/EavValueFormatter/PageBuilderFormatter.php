<?php

declare(strict_types=1);

namespace AvS\ScopeHint\Service\EavValueFormatter;

use Magento\Framework\Escaper;
use Magento\Framework\Filter\StripTags;

/**
 * Formats Page Builder EAV values as short, readable scope-hint previews.
 *
 * Page Builder is an optional Magento module, so detection deliberately uses
 * the UI metadata added by its WYSIWYG config processor instead of referencing
 * a Page Builder class directly.
 */
class PageBuilderFormatter
{
    private const MAX_LENGTH = 80;

    /** @var StripTags */
    private $stripTags;

    /** @var Escaper */
    private $escaper;

    public function __construct(StripTags $stripTags, Escaper $escaper)
    {
        $this->stripTags = $stripTags;
        $this->escaper = $escaper;
    }

    /**
     * @param array<string, mixed> $fieldMeta
     */
    public function supports(array $fieldMeta): bool
    {
        $wysiwygConfig = $fieldMeta['wysiwygConfigData'] ?? [];
        $enabled = is_array($wysiwygConfig)
            ? ($wysiwygConfig['is_pagebuilder_enabled'] ?? false)
            : false;

        // Retain compatibility with integrations that expose the flag directly.
        if ($enabled === false) {
            $enabled = $fieldMeta['is_pagebuilder_enabled'] ?? false;
        }

        return $enabled === true || $enabled === 1 || $enabled === '1';
    }

    /**
     * @param mixed $value
     */
    public function format($value): string
    {
        if ($value === null || $value === '') {
            return $this->escape((string) __('(Page Builder content differs)'));
        }

        if (!is_scalar($value) && !(is_object($value) && method_exists($value, '__toString'))) {
            return $this->escape((string) __('(Page Builder content differs)'));
        }

        // The "HTML Code" content type stores its markup entity-encoded. Decoding only that element
        // keeps literal "&lt;value&gt;" text elsewhere from being stripped as a tag.
        $content = preg_replace_callback(
            '#(<div\b[^>]*\bdata-content-type=(["\'])html\2[^>]*>)(.*?)(</div\s*>)#is',
            function (array $match): string {
                $markup = html_entity_decode($match[3], ENT_QUOTES | ENT_HTML5, 'UTF-8');

                return $match[1] . ' ' . $this->stripMarkup($markup) . ' ' . $match[4];
            },
            (string) $value
        );

        // A PCRE limit hit on a very large value would otherwise preview the undecoded markup.
        if ($content === null) {
            return $this->escape((string) __('(Page Builder content differs)'));
        }

        $preview = html_entity_decode($this->stripMarkup($content), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $normalized = preg_replace('/[\p{Z}\s]+/u', ' ', $preview);
        $preview = trim($normalized !== null ? $normalized : $preview);

        if (mb_strlen($preview) > self::MAX_LENGTH) {
            $preview = mb_substr($preview, 0, self::MAX_LENGTH) . '...';
        }

        $label = (string) __('(Page Builder content differs)');

        return $this->escape($preview !== '' ? $preview . ' ' . $label : $label);
    }

    private function stripMarkup(string $content): string
    {
        $content = preg_replace('#<(script|style)\b[^>]*>.*?</\1\s*>#is', ' ', $content) ?? $content;
        $content = preg_replace(
            '/<\s*\/?\s*(?:address|article|aside|blockquote|br|div|figcaption|figure|footer|h[1-6]|header|hr|li|main|nav|ol|p|pre|section|table|td|th|tr|ul)\b[^>]*>/i',
            ' ',
            $content
        ) ?? $content;

        return (string) $this->stripTags->filter($content);
    }

    private function escape(string $value): string
    {
        return (string) $this->escaper->escapeHtml($value);
    }
}
