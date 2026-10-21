<?php

declare(strict_types=1);

namespace AvS\ScopeHint\Plugin;

use AvS\ScopeHint\Block\Adminhtml\System\Config\Popover;
use AvS\ScopeHint\Service\ScopeOverrideDetectorInterface;
use Magento\Config\Block\System\Config\Form\Field as FieldBlock;
use Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray;
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

    /** @var array<string, string> */
    private $valueCellHtmlByElementId = [];

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
        $field = $this->structure->getElement($structurePath);

        if (!$field instanceof StructureField) {
            $field = $this->structure->getElementByConfigPath((string) ($fieldConfig['config_path'] ?? $structurePath));
        }

        if (!$field instanceof StructureField) {
            return [$element];
        }

        // Ask the structure field, not field_config: plugins such as Magento_Paypal's FieldPlugin map
        // relocated groups (payment_us/free/title) back to their stored path (payment/free/title).
        $configPath = (string) ($field->getConfigPath() ?: $field->getPath());

        if ($configPath === '') {
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

        if ($html === '') {
            return [$element];
        }

        // Field-array renderers output their own template and never print after_element_html.
        if ($subject instanceof AbstractFieldArray) {
            $this->valueCellHtmlByElementId[(string) $element->getHtmlId()] = $html;
        } else {
            $element->setData('after_element_html', (string) $element->getData('after_element_html') . $html);
        }

        return [$element];
    }

    /**
     * @param FieldBlock $subject
     * @param string $result
     * @param AbstractElement $element
     * @return string
     */
    public function afterRender(FieldBlock $subject, $result, AbstractElement $element)
    {
        $elementId = (string) $element->getHtmlId();

        if (!isset($this->valueCellHtmlByElementId[$elementId]) || !is_string($result)) {
            return $result;
        }

        $popoverHtml = $this->valueCellHtmlByElementId[$elementId];
        unset($this->valueCellHtmlByElementId[$elementId]);

        $rendered = preg_replace_callback(
            '/<td class="value[^"]*">/',
            static function (array $match) use ($popoverHtml) {
                return $match[0] . $popoverHtml;
            },
            $result,
            1
        );

        return $rendered ?? $result;
    }
}
