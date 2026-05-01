<?php

declare(strict_types=1);

namespace AvS\ScopeHint\Plugin;

use AvS\ScopeHint\Api\Data\ScopeOverrideInterface;
use AvS\ScopeHint\Block\Adminhtml\System\Config\Popover;
use AvS\ScopeHint\Service\ScopeOverrideDetectorInterface;
use Magento\Config\Model\Config\Structure\Element\Field as Subject;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\LayoutInterface;

class ConfigFieldPlugin
{
    private const CONFIG_SHOW_PATH     = 'dev/debug/show_path_in_adminhtml';
    private const CONFIG_LEGACY_TOOLTIP = 'dev/debug/scopehint_legacy_tooltip';

    /** @var ScopeConfigInterface */
    private $scopeConfig;

    /** @var ScopeOverrideDetectorInterface */
    private $detector;

    /** @var LayoutInterface */
    private $layout;

    /** @var bool */
    private $isProcessing = false;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        ScopeOverrideDetectorInterface $detector,
        LayoutInterface $layout
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->detector    = $detector;
        $this->layout      = $layout;
    }

    /**
     * @param Subject $subject
     * @param string $result
     * @return string
     */
    public function afterGetTooltip(Subject $subject, $result)
    {
        $path      = $this->getPath($subject);
        $overrides = $this->detector->getOverrides($path, $subject);

        if ($overrides->isEmpty()) {
            return $result;
        }

        if ($this->scopeConfig->isSetFlag(self::CONFIG_LEGACY_TOOLTIP)) {
            $lines = $result === '' || $result === null ? [] : [$result];
            foreach ($overrides->getOverrides() as $override) {
                $lines[] = $this->renderLine($override);
            }
            return implode('<br />', $lines);
        }

        /** @var Popover $block */
        $block = $this->layout->createBlock(Popover::class);
        $block->setOverrides($overrides);
        $popoverHtml = $block->toHtml();

        if ($result === '' || $result === null) {
            return $popoverHtml;
        }
        return $result . $popoverHtml;
    }

    /**
     * @param Subject $subject
     * @param array $result
     * @return array
     */
    public function afterGetData(
        Subject $subject,
        array $result
    ) {
        if (!$this->scopeConfig->isSetFlag(self::CONFIG_SHOW_PATH)) {
            return $result;
        }

        // Guard against potential infinite loops if getData is called recursively
        // while building the path_hint
        if (!$this->isProcessing) {
            $this->isProcessing = true;
            $result['path_hint'] = '<small>' . __('Path: <code>%1</code>', $this->getPath($subject)) . '</small>';
            $this->isProcessing = false;
        }

        return $result;
    }

    private function renderLine(ScopeOverrideInterface $override): string
    {
        if ($override->getScopeType() === ScopeOverrideInterface::SCOPE_TYPE_STORES) {
            $phrase = __('Store <code>%1</code>: "%2"', $override->getScopeCode(), $override->getDisplayValue());
        } else {
            $phrase = __('Website <code>%1</code>: "%2"', $override->getScopeCode(), $override->getDisplayValue());
        }
        return (string) $phrase;
    }

    private function getPath(Subject $subject): string
    {
        return (string) ($subject->getConfigPath() ?: $subject->getPath());
    }
}
