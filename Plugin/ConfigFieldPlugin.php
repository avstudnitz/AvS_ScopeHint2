<?php

declare(strict_types=1);

namespace AvS\ScopeHint\Plugin;

use AvS\ScopeHint\Api\Data\ScopeOverrideInterface;
use AvS\ScopeHint\Service\ScopeOverrideDetectorInterface;
use Magento\Config\Model\Config\Structure\Element\Field as Subject;
use Magento\Framework\App\Config\ScopeConfigInterface;

class ConfigFieldPlugin
{
    private const CONFIG_SHOW_PATH = 'dev/debug/show_path_in_adminhtml';

    /** @var ScopeConfigInterface */
    private $scopeConfig;

    /** @var ScopeOverrideDetectorInterface */
    private $detector;

    /** @var bool */
    private $isProcessing = false;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        ScopeOverrideDetectorInterface $detector
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->detector = $detector;
    }

    /**
     * @param Subject $subject
     * @param string $result
     * @return string
     */
    public function afterGetTooltip(Subject $subject, $result)
    {
        $path = $this->getPath($subject);
        $overrides = $this->detector->getOverrides($path, $subject);

        if ($overrides->isEmpty()) {
            return $result;
        }

        $lines = $result === '' || $result === null ? [] : [$result];
        foreach ($overrides->getOverrides() as $override) {
            $lines[] = $this->renderLine($override);
        }
        return implode('<br />', $lines);
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
