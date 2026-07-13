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
 * afterGetData adds escaped popover markup in a companion data key while
 * preserving the original field value for native sorting, filtering and label
 * formatting.
 *
 * A dedicated cell template is selected by CatalogColumnFactoryPlugin for
 * dynamic EAV columns and by product_listing.xml for Magento's static Name
 * column. It text-binds native values and HTML-binds only this trusted markup.
 *
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

                $item['__scopehint_' . $attributeCode] = $this->cellPopover->render($storeOverrides);
            }
        }

        return $result;
    }
}
