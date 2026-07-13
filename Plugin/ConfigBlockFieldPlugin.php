<?php

declare(strict_types=1);

namespace AvS\ScopeHint\Plugin;

use AvS\ScopeHint\Block\Adminhtml\System\Config\Popover;
use AvS\ScopeHint\Service\ScopeOverrideDetectorInterface;
use Magento\Config\Block\System\Config\Form\Field as FieldBlock;
use Magento\Config\Model\Config\Structure;
use Magento\Config\Model\Config\Structure\Element\Field as StructureField;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\View\LayoutInterface;

class ConfigBlockFieldPlugin
{
    private const CONFIG_LEGACY_TOOLTIP = 'dev/debug/scopehint_legacy_tooltip';

    /** @var ScopeOverrideDetectorInterface */
    private $detector;

    /** @var LayoutInterface */
    private $layout;

    /** @var ScopeConfigInterface */
    private $scopeConfig;

    /** @var Structure */
    private $structure;

    public function __construct(
        ScopeOverrideDetectorInterface $detector,
        LayoutInterface $layout,
        ScopeConfigInterface $scopeConfig,
        Structure $structure
    ) {
        $this->detector = $detector;
        $this->layout = $layout;
        $this->scopeConfig = $scopeConfig;
        $this->structure = $structure;
    }

    /**
     * Add the modern popover next to the input, rather than inside Magento's
     * hover-only help tooltip.
     *
     * @return AbstractElement[]
     */
    public function beforeRender(FieldBlock $subject, AbstractElement $element): array
    {
        if ($this->scopeConfig->isSetFlag(self::CONFIG_LEGACY_TOOLTIP)) {
            return [$element];
        }

        $fieldConfig = (array) $element->getData('field_config');
        $structurePath = trim((string) ($fieldConfig['path'] ?? '') . '/' . (string) ($fieldConfig['id'] ?? ''), '/');
        $configPath = (string) ($fieldConfig['config_path'] ?? $structurePath);
        $field = $this->structure->getElement($structurePath);

        if (!$field instanceof StructureField) {
            $field = $this->structure->getElementByConfigPath($configPath);
        }

        if (!$field instanceof StructureField || $configPath === '') {
            return [$element];
        }

        $overrides = $this->detector->getOverrides($configPath, $field);

        if ($overrides->isEmpty()) {
            return [$element];
        }

        /** @var Popover $block */
        $block = $this->layout->createBlock(Popover::class);
        $block->setOverrides($overrides);
        $block->setFieldHtmlId('scopehint-panel-' . preg_replace('/[^a-zA-Z0-9_-]/', '-', $element->getHtmlId()));
        $html = $block->toHtml();

        if ($html !== '') {
            $element->setData('after_element_html', (string) $element->getData('after_element_html') . $html);
        }

        return [$element];
    }
}
