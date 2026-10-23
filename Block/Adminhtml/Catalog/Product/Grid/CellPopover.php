<?php

declare(strict_types=1);

namespace AvS\ScopeHint\Block\Adminhtml\Catalog\Product\Grid;

use Magento\Framework\Escaper;
use Magento\Framework\Phrase;

/**
 * Renders the inline scope-override popover HTML for a single product grid cell.
 *
 * The normal grid value is rendered separately through a text binding. This
 * class returns only trusted ScopeHint markup for the companion HTML binding.
 *
 * The data-scopehint-popover attribute causes popover-bootstrap.js to bind the
 * JS behaviour automatically via its MutationObserver when the enriched HTML
 * lands in the grid.
 */
class CellPopover
{
    /** @var Escaper */
    private $escaper;

    public function __construct(Escaper $escaper)
    {
        $this->escaper = $escaper;
    }

    /**
     * Render cell HTML for a product attribute column.
     *
     * @param array<int, array<string, string>> $overrides storeId → [displayValue, storeName, storeCode, websiteCode]
     * @return string
     */
    public function render(array $overrides): string
    {
        if (empty($overrides)) {
            return '';
        }

        $byWebsite = [];

        foreach ($overrides as $override) {
            $websiteCode = (string) ($override['websiteCode'] ?? '');
            $byWebsite[$websiteCode][] = $override;
        }

        $panelId      = 'scopehint-cell-' . bin2hex(random_bytes(6));
        $count        = count($overrides);
        $triggerLabel = $this->escaper->escapeHtmlAttr(
            (string) new Phrase('%1 scope override(s) — click to expand', [$count])
        );
        $panelLabel  = $this->escaper->escapeHtmlAttr(
            (string) new Phrase('%1 scope overrides', [$count])
        );
        $panelIdAttr = $this->escaper->escapeHtmlAttr($panelId);
        $closeLabel  = $this->escaper->escapeHtmlAttr((string) new Phrase('Close'));

        $html = '<span class="scopehint-popover scopehint-popover--cell" data-scopehint-popover="1">';
        $html .= '<button type="button"'
            . ' class="scopehint-trigger"'
            . ' aria-expanded="false"'
            . ' aria-controls="' . $panelIdAttr . '"'
            . ' aria-label="' . $triggerLabel . '">';
        $html .= '<span class="scopehint-badge">' . (int) $count . '</span>';
        $html .= '</button>';
        $html .= '<div class="scopehint-panel scopehint-panel--cell"'
            . ' id="' . $panelIdAttr . '"'
            . ' role="dialog"'
            . ' aria-modal="false"'
            . ' aria-label="' . $panelLabel . '"'
            . ' hidden>';
        $html .= '<header class="scopehint-panel-header">';
        $html .= '<button type="button" class="scopehint-close"'
            . ' aria-label="' . $closeLabel . '">&#x00D7;</button>';
        $html .= '</header>';
        $html .= '<ul class="scopehint-websites">';

        foreach ($byWebsite as $websiteCode => $websiteOverrides) {
            $websiteCodeAttr = $this->escaper->escapeHtmlAttr((string) $websiteCode);
            $storeCount      = count($websiteOverrides);
            $summaryLabel    = $this->escaper->escapeHtml(
                (string) new Phrase('Website: %1 (%2 override(s))', [(string) $websiteCode, $storeCount])
            );

            $html .= '<li data-website-code="' . $websiteCodeAttr . '">';
            $html .= '<details open>';
            $html .= '<summary>' . $summaryLabel . '</summary>';
            $html .= '<ul>';

            foreach ($websiteOverrides as $override) {
                $storeName       = $this->escaper->escapeHtml((string) ($override['storeName'] ?? ''));
                $storeCode       = $this->escaper->escapeHtml((string) ($override['storeCode'] ?? ''));
                $storeCodeAttr   = $this->escaper->escapeHtmlAttr((string) ($override['storeCode'] ?? ''));
                $rawDisplayValue = (string) ($override['displayValue'] ?? '');
                $displayValue    = $this->escaper->escapeHtml(
                    $rawDisplayValue !== '' ? $rawDisplayValue : (string) __('(empty)')
                );

                $html .= '<li data-store-code="' . $storeCodeAttr . '">';
                $html .= '<div class="scopehint-store-meta">';
                $html .= '<span class="scopehint-store-name">' . $storeName . '</span>';
                $html .= '<code class="scopehint-store-code">' . $storeCode . '</code>';
                $html .= '</div>';
                $html .= '<div class="scopehint-store-value">' . $displayValue . '</div>';
                $html .= '</li>';
            }

            $html .= '</ul>';
            $html .= '</details>';
            $html .= '</li>';
        }

        $html .= '</ul>';
        $html .= '</div>';
        $html .= '</span>';

        return $html;
    }
}
