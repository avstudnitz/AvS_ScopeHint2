<?php

declare(strict_types=1);

namespace AvS\ScopeHint\Plugin;

use AvS\ScopeHint\Block\Adminhtml\Catalog\Product\Grid\CellPopover;
use AvS\ScopeHint\Service\ProductOverrideAggregator;
use Magento\Catalog\Ui\DataProvider\Product\ProductDataProvider;
use Magento\Framework\App\Config\ScopeConfigInterface;

/**
 * Enriches product grid cells with inline scope-override popovers.
 *
 * afterGetData: for each attribute column that has per-store overrides, replaces
 * the plain cell value with HTML that includes the default value and a pill button
 * that opens a flat per-store list popover.
 *
 * bodyTmpl is flipped to ui/grid/cells/html by CatalogColumnFactoryPlugin, which
 * plugs ColumnFactory::create() — the correct hook because EAV columns are added
 * after getMeta() runs (Catalog\Ui\Component\Listing\Columns::prepare()).
 *
 * Trade-off: enriched cells contain HTML strings, so Magento's native grid
 * sort/filter operates on the HTML string for rows that have overrides. Plain
 * rows (no overrides) are unaffected.
 */
class ProductGridDataProviderPlugin
{
    private const CONFIG_LEGACY_TOOLTIP = 'dev/debug/scopehint_legacy_tooltip';

    /** @var ProductOverrideAggregator */
    private $aggregator;

    /** @var CellPopover */
    private $cellPopover;

    /** @var ScopeConfigInterface */
    private $scopeConfig;

    public function __construct(
        ProductOverrideAggregator $aggregator,
        CellPopover $cellPopover,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->aggregator  = $aggregator;
        $this->cellPopover = $cellPopover;
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Enrich grid item cells with inline scope-override popovers.
     *
     * @param ProductDataProvider $subject
     * @param array $result
     * @return array
     */
    public function afterGetData(ProductDataProvider $subject, array $result): array
    {
        if ($this->scopeConfig->isSetFlag(self::CONFIG_LEGACY_TOOLTIP)) {
            return $result;
        }

        if (empty($result['items'])) {
            return $result;
        }

        $productIds = [];

        foreach ($result['items'] as $item) {
            if (isset($item['entity_id'])) {
                $productIds[] = (int) $item['entity_id'];
            }
        }

        if (empty($productIds)) {
            return $result;
        }

        $overridesMap = $this->aggregator->getOverrides($productIds);

        if (empty($overridesMap)) {
            return $result;
        }

        foreach ($result['items'] as &$item) {
            $entityId = isset($item['entity_id']) ? (int) $item['entity_id'] : 0;

            if (!isset($overridesMap[$entityId])) {
                continue;
            }

            $productOverrides = $overridesMap[$entityId];

            foreach ($productOverrides as $attributeCode => $storeOverrides) {
                // Only modify columns that already exist in the item data.
                if (!array_key_exists($attributeCode, $item)) {
                    continue;
                }

                $defaultValue = (string) ($item[$attributeCode] ?? '');
                $item[$attributeCode] = $this->cellPopover->render($defaultValue, $storeOverrides);
            }
        }

        return $result;
    }
}
