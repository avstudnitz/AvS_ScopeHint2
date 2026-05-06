<?php

declare(strict_types=1);

namespace AvS\ScopeHint\Plugin;

use AvS\ScopeHint\Block\Adminhtml\Catalog\Product\Grid\CellPopover;
use AvS\ScopeHint\Service\ProductOverrideAggregator;
use Magento\Catalog\Ui\DataProvider\Product\ProductDataProvider;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Stdlib\ArrayManager;

/**
 * Enriches product grid cells with inline scope-override popovers.
 *
 * afterGetData: for each attribute column that has per-store overrides, replaces
 * the plain cell value with HTML that includes the default value and a pill button
 * that opens a flat per-store list popover.
 *
 * afterGetMeta: sets bodyTmpl = ui/grid/cells/html on every column whose EAV
 * attribute is store-scoped and not an image type. This is necessary because
 * Magento's default column template HTML-escapes the cell value — if the cell
 * contains injected HTML it must be rendered raw.
 *
 * Trade-off: enriched cells contain HTML strings, so Magento's native grid
 * sort/filter operates on the HTML string for rows that have overrides. Plain
 * rows (no overrides) are unaffected.
 */
class ProductGridDataProviderPlugin
{
    private const CONFIG_LEGACY_TOOLTIP = 'dev/debug/scopehint_legacy_tooltip';

    /**
     * frontend_input values that must not receive the bodyTmpl override.
     *
     * @var string[]
     */
    private static $skippedInputTypes = ['image', 'media_image', 'gallery'];

    /** @var ProductOverrideAggregator */
    private $aggregator;

    /** @var CellPopover */
    private $cellPopover;

    /** @var ScopeConfigInterface */
    private $scopeConfig;

    /** @var EavConfig */
    private $eavConfig;

    /** @var ArrayManager */
    private $arrayManager;

    public function __construct(
        ProductOverrideAggregator $aggregator,
        CellPopover $cellPopover,
        ScopeConfigInterface $scopeConfig,
        EavConfig $eavConfig,
        ArrayManager $arrayManager
    ) {
        $this->aggregator   = $aggregator;
        $this->cellPopover  = $cellPopover;
        $this->scopeConfig  = $scopeConfig;
        $this->eavConfig    = $eavConfig;
        $this->arrayManager = $arrayManager;
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

    /**
     * Set bodyTmpl = ui/grid/cells/html on store-scoped, non-image EAV attribute columns.
     *
     * @param ProductDataProvider $subject
     * @param array $meta
     * @return array
     */
    public function afterGetMeta(ProductDataProvider $subject, array $meta): array
    {
        if ($this->scopeConfig->isSetFlag(self::CONFIG_LEGACY_TOOLTIP)) {
            return $meta;
        }

        // The product listing meta has a top-level 'product_columns' container
        // whose children map column names to their config. We walk all top-level
        // containers to be compatible with custom container names.
        foreach ($meta as $containerName => $container) {
            $children = $container['children'] ?? [];

            foreach ($children as $columnName => $columnNode) {
                if (!$this->columnNeedsHtmlTemplate($columnName)) {
                    continue;
                }

                $path = $containerName . '/children/' . $columnName;

                $meta = $this->arrayManager->merge(
                    $path . '/arguments/data/config',
                    $meta,
                    ['bodyTmpl' => 'ui/grid/cells/html']
                );
            }
        }

        return $meta;
    }

    /**
     * Determine whether a column (by its name / dataScope) should use the HTML body template.
     *
     * Returns true when the EAV attribute for the column name is store-scoped and
     * has a non-image frontend_input. Returns false for unknown attributes (not EAV)
     * or attributes where the scope check fails.
     */
    private function columnNeedsHtmlTemplate(string $columnName): bool
    {
        try {
            $attribute = $this->eavConfig->getAttribute('catalog_product', $columnName);

            if (!$attribute || !$attribute->getAttributeId()) {
                return false;
            }

            // Must be store/website-scoped, not global.
            // getIsGlobal() lives on Magento\Catalog\Model\ResourceModel\Eav\Attribute;
            // fall back to getData() which works on all DataObject-based attributes.
            $isGlobal = method_exists($attribute, 'getIsGlobal')
                ? (int) $attribute->getIsGlobal()
                : (int) $attribute->getData('is_global');

            if ($isGlobal === \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL) {
                return false;
            }

            // Skip image/media/gallery.
            if (in_array($attribute->getFrontendInput(), self::$skippedInputTypes, true)) {
                return false;
            }

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
