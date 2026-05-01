<?php

declare(strict_types=1);

namespace AvS\ScopeHint\Plugin;

use AvS\ScopeHint\Block\Adminhtml\Eav\Popover;
use AvS\ScopeHint\Service\OverrideSourcePool;
use Magento\Catalog\Model\Category\DataProvider;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Stdlib\ArrayManager;
use Magento\Framework\View\LayoutInterface;

/**
 * Injects scope-override popovers into category EAV form field meta.
 *
 * Override detection is delegated to OverrideSourcePool, which dispatches to
 * the first registered OverrideSourceInterface whose supports() returns true.
 * The default source (EavCategoryOverrideSource) covers real Magento EAV
 * category attributes. Third-party modules can register additional sources via
 * di.xml to cover synthetic fields.
 *
 * The popover HTML is written into the `additionalInfo` field-meta key, which
 * Knockout renders via html= inside `.admin__field-control`. A small JS
 * bootstrap (popover-bootstrap.js) handles re-binding after KO injection.
 *
 * BC note: constructor changed in 1.4.0 — EavScopeOverrideDetector, EavConfig,
 * RequestInterface, and ArrayManager were replaced by OverrideSourcePool,
 * LayoutInterface, and ScopeConfigInterface. See CHANGELOG.md for details.
 */
class CategoryDataProviderPlugin
{
    private const CONFIG_LEGACY_TOOLTIP = 'dev/debug/scopehint_legacy_tooltip';

    /** @var OverrideSourcePool */
    private $overrideSourcePool;

    /** @var LayoutInterface */
    private $layout;

    /** @var ScopeConfigInterface */
    private $scopeConfig;

    /** @var ArrayManager */
    private $arrayManager;

    public function __construct(
        OverrideSourcePool $overrideSourcePool,
        LayoutInterface $layout,
        ScopeConfigInterface $scopeConfig,
        ArrayManager $arrayManager
    ) {
        $this->overrideSourcePool = $overrideSourcePool;
        $this->layout = $layout;
        $this->scopeConfig = $scopeConfig;
        $this->arrayManager = $arrayManager;
    }

    /**
     * Inject scope-override popovers into the category form meta.
     *
     * @param DataProvider $subject
     * @param array $result
     * @return array
     */
    public function afterGetMeta(DataProvider $subject, array $result): array
    {
        $category = $subject->getCurrentCategory();

        if ($category === null || !$category->getId()) {
            return $result;
        }

        $categoryId = (int) $category->getId();
        $isLegacy = $this->scopeConfig->isSetFlag(self::CONFIG_LEGACY_TOOLTIP);

        foreach ($this->iterateFieldMeta($result) as $attributePath => $fieldMeta) {
            $overrides = $this->overrideSourcePool->resolve('category', $categoryId, $fieldMeta);

            if ($overrides === null || $overrides->isEmpty()) {
                continue;
            }

            if ($isLegacy) {
                $lines = [];

                foreach ($overrides->getOverrides() as $override) {
                    $lines[] = $override->getScopeName() . ': ' . $override->getDisplayValue();
                }

                $result = $this->arrayManager->merge(
                    $attributePath . '/arguments/data/config',
                    $result,
                    ['tooltip' => ['description' => implode('<br>', $lines)]]
                );

                continue;
            }

            /** @var Popover $popoverBlock */
            $popoverBlock = $this->layout->createBlock(Popover::class);
            $popoverBlock->setOverrides($overrides);
            $popoverHtml = $popoverBlock->toHtml();

            if ($popoverHtml !== '') {
                $result = $this->arrayManager->merge(
                    $attributePath . '/arguments/data/config',
                    $result,
                    ['additionalInfo' => $popoverHtml]
                );
            }
        }

        return $result;
    }

    /**
     * Walk the meta tree and yield every field-level config keyed by its path.
     *
     * @param array $meta Top-level meta array (fieldset → children → field).
     * @return iterable<string, array> Yields $attributePath => $fieldMeta pairs.
     */
    private function iterateFieldMeta(array $meta): iterable
    {
        foreach ($meta as $fieldsetName => $fieldset) {
            $children = $fieldset['children'] ?? [];

            foreach ($children as $fieldName => $fieldNode) {
                $fieldMeta = $fieldNode['arguments']['data']['config'] ?? null;

                if (!is_array($fieldMeta)) {
                    continue;
                }

                // Derive the path that arrayManager->merge() expects.
                $path = $fieldsetName . '/children/' . $fieldName;

                yield $path => $fieldMeta;
            }
        }
    }
}
