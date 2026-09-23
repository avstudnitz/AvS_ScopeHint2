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
 * dynamic EAV columns and by product_listing.xml for Magento's static Name,
 * Visibility and Status columns. It text-binds native values and HTML-binds
 * only this trusted markup.
 *
 * Grid columns register their field through addField(); those codes decide
 * which attributes are scanned. Row keys alone are not enough: an attribute
 * with no default-scope value is missing from the row data.
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

    /** @var array<int, array<string, true>> */
    private $gridFieldCodesByProvider = [];

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
     * @param ProductDataProvider $subject
     * @param string|string[] $field
     * @param string|null $alias
     * @return null
     */
    public function beforeAddField(ProductDataProvider $subject, $field, $alias = null)
    {
        foreach ((array) $field as $fieldCode) {
            $this->gridFieldCodesByProvider[spl_object_id($subject)][(string) $fieldCode] = true;
        }

        return null;
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
        $fieldCodes = $this->gridFieldCodesByProvider[spl_object_id($subject)] ?? [];

        foreach ($result['items'] as $item) {
            if (isset($item['entity_id'])) {
                $productIds[] = (int) $item['entity_id'];
            }
            foreach (array_keys($item) as $fieldCode) {
                $fieldCodes[(string) $fieldCode] = true;
            }
        }

        if (empty($productIds)) {
            return $result;
        }

        $overridesMap = $this->aggregator->getOverrides($productIds, array_keys($fieldCodes));

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
                if (!isset($fieldCodes[$attributeCode])) {
                    continue;
                }

                $item['__scopehint_' . $attributeCode] = $this->cellPopover->render($storeOverrides);
            }
        }

        return $result;
    }
}
