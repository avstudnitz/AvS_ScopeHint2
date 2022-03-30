<?php
namespace AvS\ScopeHint\Plugin;

use Magento\Config\Model\Config\Structure\Element\Field as Subject;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Phrase;
use Magento\Store\Api\WebsiteRepositoryInterface;
use Magento\Store\Api\StoreRepositoryInterface;
use Magento\Store\Api\Data\WebsiteInterface;
use Magento\Store\Api\Data\StoreInterface;

class ConfigFieldPlugin
{
    const SCOPE_TYPE_WEBSITES = 'websites';
    const SCOPE_TYPE_STORES = 'stores';

    private const CONFIG_SHOW_PATH = 'dev/debug/show_path_in_adminhtml';

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;
    /**
     * @var WebsiteRepositoryInterface
     */
    private $websiteRepository;
    /**
     * @var StoreRepositoryInterface
     */
    private $storeRepository;
    /**
     * @var RequestInterface
     */
    private $request;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        WebsiteRepositoryInterface $websiteRepository,
        StoreRepositoryInterface $storeRepository,
        RequestInterface $request
    )
    {
        $this->scopeConfig = $scopeConfig;
        $this->websiteRepository = $websiteRepository;
        $this->storeRepository = $storeRepository;
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
            if (!$this->isWebsiteSelected($website)) {
                if ($scopeLine = $this->getScopeHint($this->getPath($subject), self::SCOPE_TYPE_WEBSITES, $website)) {
                    $lines[] = $scopeLine;
                }
            }
        }
        foreach($this->storeRepository->getList() as $store) {
            if (!$this->isStoreSelected($store)) {
                if ($scopeLine = $this->getScopeHint($this->getPath($subject), self::SCOPE_TYPE_STORES, $store)) {
                    $lines[] = $scopeLine;
                }
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
        if (!$this->scopeConfig->isSetFlag(self::CONFIG_SHOW_PATH)) {
            return $result;
        }

        $resultIsPhrase = $result instanceof Phrase;
        if ($resultIsPhrase) {
            $phrase = $result;
            $result = $phrase->getText();
            $arguments = $phrase->getArguments();
        }

        if (strlen(trim($result))) {
            $result .= '<br />';
        }
        $result .= __('Path: <code>%1</code>', $this->getPath($subject));

        if ($resultIsPhrase) {
            $result = new Phrase($result, $arguments);
        }

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
     * @param StoreInterface $store
     * @return bool
     */
    private function isStoreSelected($store)
    {
        return $store->getId() == $this->request->getParam('store');
    }

    /**
     * @param string $path
     * @param string $scopeType
     * @param WebsiteInterface|StoreInterface $scope
     * @return string
     */
    private function getScopeHint($path, $scopeType, $scope)
    {
        $scopeLine = '';
        $currentValue = $this->scopeConfig->getValue($path);
        $scopeValue = $this->scopeConfig->getValue($path, $scopeType, $scope->getId());
        if ($scopeValue != $currentValue) {
            switch($scopeType) {
                case self::SCOPE_TYPE_STORES:
                    return __('Store <code>%1</code>: "%2"', $scope->getCode(), $scopeValue);
                case self::SCOPE_TYPE_WEBSITES:
                    return __('Website <code>%1</code>: "%2"', $scope->getCode(), $scopeValue);
            }
        }
        return $scopeLine;
    }
}
