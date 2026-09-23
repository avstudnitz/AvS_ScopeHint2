<?php

declare(strict_types=1);

namespace AvS\ScopeHint\Plugin;

use AvS\ScopeHint\Block\Adminhtml\Eav\Popover;
use AvS\ScopeHint\Service\OverrideSourcePool;
use Magento\Catalog\Ui\DataProvider\Product\Form\Modifier\Eav;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Escaper;
use Magento\Framework\Registry;
use Magento\Framework\View\LayoutInterface;

/**
 * Injects scope-override popovers into product EAV form field meta.
 *
 * Override detection is delegated to OverrideSourcePool, which dispatches to
 * the first registered OverrideSourceInterface whose supports() returns true.
 * The default source (EavProductOverrideSource) covers real Magento EAV
 * attributes. Third-party modules can register additional sources via di.xml to
 * cover synthetic fields (e.g. Hyva SeoSuite metadata).
 *
 * The popover HTML is written into the `additionalInfo` field-meta key, which
 * Knockout renders via html= inside `.admin__field-control`. A small JS
 * bootstrap (popover-bootstrap.js) handles re-binding after KO injection because
 * mage/apply does not re-run on KO-injected nodes.
 *
 * BC note: constructor changed in 1.4.0 — StoreManagerInterface, Registry, and
 * ProductRepositoryInterface were moved to EavProductOverrideSource. Registry
 * is retained here because it is needed to retrieve the current product ID.
 * See CHANGELOG.md for migration notes.
 */
class ProductEavDataProviderPlugin
{
    private const CONFIG_LEGACY_TOOLTIP = 'dev/debug/scopehint_legacy_tooltip';

    /** @var OverrideSourcePool */
    private $overrideSourcePool;

    /** @var LayoutInterface */
    private $layout;

    /** @var ScopeConfigInterface */
    private $scopeConfig;

    /** @var Registry */
    private $registry;

    /** @var Escaper */
    private $escaper;

    public function __construct(
        OverrideSourcePool $overrideSourcePool,
        LayoutInterface $layout,
        ScopeConfigInterface $scopeConfig,
        Registry $registry,
        Escaper $escaper
    ) {
        $this->overrideSourcePool = $overrideSourcePool;
        $this->layout = $layout;
        $this->scopeConfig = $scopeConfig;
        $this->registry = $registry;
        $this->escaper = $escaper;
    }

    /**
     * @param Eav   $subject
     * @param mixed $result
     * @return mixed
     */
    public function afterSetupAttributeMeta(Eav $subject, $result)
    {
        if (!isset($result['arguments']['data']['config'])) {
            return $result;
        }

        $product = $this->registry->registry('current_product');

        if ($product === null || $product->getId() === null) {
            return $result;
        }

        $productId = (int) $product->getId();
        $fieldMeta = $result['arguments']['data']['config'];
        $overrides = $this->overrideSourcePool->resolve(
            'product',
            $productId,
            $fieldMeta,
            (int) $product->getStoreId()
        );

        if ($overrides === null || $overrides->isEmpty()) {
            return $result;
        }

        if ($this->scopeConfig->isSetFlag(self::CONFIG_LEGACY_TOOLTIP)) {
            $existingDescription = (string) ($fieldMeta['tooltip']['description'] ?? '');
            $lines = $existingDescription !== '' ? [$existingDescription] : [];

            foreach ($overrides->getOverrides() as $override) {
                $lines[] = $this->escaper->escapeHtml($override->getScopeName())
                    . ': ' . $override->getDisplayValue();
            }

            $result['arguments']['data']['config']['tooltip']['description'] = implode('<br>', $lines);

            return $result;
        }

        /** @var Popover $popoverBlock */
        $popoverBlock = $this->layout->createBlock(Popover::class);
        $popoverBlock->setOverrides($overrides);
        $popoverHtml = $popoverBlock->toHtml();

        if ($popoverHtml !== '') {
            $result['arguments']['data']['config']['additionalInfo'] =
                (string) ($fieldMeta['additionalInfo'] ?? '') . $popoverHtml;
        }

        return $result;
    }
}
