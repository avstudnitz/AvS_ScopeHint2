<?php
namespace AvS\ScopeHint\Plugin;

use Magento\Config\Model\Config\Structure\Element\Field as Subject;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Store\Api\WebsiteRepositoryInterface;
use Magento\Store\Api\Data\WebsiteInterface;

class ConfigFieldPlugin
{
    const SCOPE_TYPE_WEBSITES = 'websites';
    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;
    /**
     * @var WebsiteRepositoryInterface
     */
    private $websiteRepository;
    /**
     * @var RequestInterface
     */
    private $request;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        WebsiteRepositoryInterface $websiteRepository,
        RequestInterface $request
    )
    {
        $this->scopeConfig = $scopeConfig;
        $this->websiteRepository = $websiteRepository;
        $this->request = $request;
    }

    /**
     * @param Subject $subject
     * @param string $result
     * @return string
     */
    public function afterGetTooltip(Subject $subject, $result)
    {
        $lines = [$result];
        foreach($this->websiteRepository->getList() as $website) {
            if ($this->isWebsiteSelected($website)) {
                continue;
            }

            if ($scopeLine = $this->getScopeHint($this->getPath($subject), self::SCOPE_TYPE_WEBSITES, $website)) {
                $lines[] = $scopeLine;
            }
        }
        return implode('<br />', array_filter($lines));
    }

    /**
     * @param Subject $subject
     * @param string $result
     * @return string
     */
    public function afterGetComment(Subject $subject, $result)
    {
        if (strlen(trim($result))) {
            $result .= '<br />';
        }
        $result .= __('Path: <code>%1</code>', $this->getPath($subject));
        return $result;
    }

    /**
     * @param Subject $subject
     * @return string
     */
    private function getPath(Subject $subject)
    {
        return $path = $subject->getConfigPath() ?: $subject->getPath();
    }

    /**
     * @param WebsiteInterface $website
     * @return bool
     */
    private function isWebsiteSelected($website)
    {
        return $website->getId() == $this->request->getParam('website');
    }

    /**
     * @param $path
     * @param $scopeType
     * @param $website
     * @return \Magento\Framework\Phrase|string
     */
    private function getScopeHint($path, $scopeType, $website)
    {
        $scopeLine = '';
        $currentValue = $this->scopeConfig->getValue($path);
        $scopeValue = $this->scopeConfig->getValue($path, $scopeType, $website->getId());
        if ($scopeValue != $currentValue) {
            $scopeLine = __('Website <code>%1</code>: "%2"', $website->getCode(), $scopeValue);
            return $scopeLine;
        }
        return $scopeLine;
    }
}