<?php
namespace AvS\ScopeHint\Plugin;

use Magento\Config\Model\Config\Structure\Element\Field as Subject;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Escaper;
use Magento\Framework\Phrase;
use Magento\Store\Api\WebsiteRepositoryInterface;
use Magento\Store\Api\StoreRepositoryInterface;
use Magento\Store\Api\Data\WebsiteInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\ScopeInterface;

class ConfigFieldPlugin
{
    const SCOPE_TYPE_WEBSITES = 'websites';
    const SCOPE_TYPE_STORES = 'stores';

    /** @var Escaper */
    private $escaper;

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
        Escaper $escaper,
        ScopeConfigInterface $scopeConfig,
        WebsiteRepositoryInterface $websiteRepository,
        StoreRepositoryInterface $storeRepository,
        RequestInterface $request
    ) {
        $this->escaper = $escaper;
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
            if ($this->getWebsiteParam() || $this->getStoreParam()) {
                continue;
            }
            // Only show website specific values in default scope
            if ($scopeLine = $this->getScopeHint($subject, self::SCOPE_TYPE_WEBSITES, $website)) {
                $lines[] = $scopeLine;
            }
        }
        foreach($this->storeRepository->getList() as $store) {
            if ($this->getStoreParam($store)) {
                continue;
            }
            if (($websiteId = $this->getWebsiteParam()) && ($store->getWebsiteId() != $websiteId)) {
                continue;
            }
            // Only show store specific values in default scope and in parent website scope
            if ($scopeLine = $this->getScopeHint($subject, self::SCOPE_TYPE_STORES, $store)) {
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

        if ($result instanceof Phrase) {
            $result = (string) $result;
        }

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
     * @param Subject $field
     * @param string $scopeType
     * @param WebsiteInterface|StoreInterface $scope
     * @return string
     */
    private function getScopeHint(Subject $field, $scopeType, $scope)
    {
        $path = $this->getPath($field);
        $scopeLine = '';
        if ($websiteId = $this->getWebsiteParam()) {
            $currentValue = $this->scopeConfig->getValue(
                $path,
                ScopeInterface::SCOPE_WEBSITE,
                $websiteId
            );
        } else {
            $currentValue = $this->scopeConfig->getValue($path);
        }
        $scopeValue = $this->scopeConfig->getValue($path, $scopeType, $scope->getId());

        if (is_array($currentValue) || is_array($scopeValue)) {
            return $scopeLine;
        }

        $currentValue = (string) $currentValue;
        $scopeValue = (string) $scopeValue;

        if ($scopeValue != $currentValue) {
            $scopeValue = $this->escaper->escapeHtml($scopeValue);

            switch($scopeType) {
                case self::SCOPE_TYPE_STORES:
                    return __(
                        'Store <code>%1</code>: "%2"',
                        $scope->getCode(),
                        $this->getValueLabel($field, $scopeValue)
                    );
                case self::SCOPE_TYPE_WEBSITES:
                    return __(
                        'Website <code>%1</code>: "%2"',
                        $scope->getCode(),
                        $this->getValueLabel($field, $scopeValue)
                    );
            }
        }
        return $scopeLine;
    }

    private function getValueLabel(Subject $field, string $scopeValue): string
    {
        $scopeValue = trim($scopeValue);
        if ($field->hasOptions()) {
            if ($field->getType() === 'multiselect' && strpos($scopeValue, ',') !== false) {
                return implode(
                    ', ',
                    array_map(
                        function ($key) use ($field) {
                            return $this->getValueLabel($field, $key);
                        },
                        explode(',', $scopeValue)
                    )
                );
            }
            foreach ($field->getOptions() as $option) {
                if (is_array($option) && $option['value'] == $scopeValue) {
                    return $option['label'];
                }
            }
        }
        return $scopeValue;
    }

    private function getWebsiteParam(): ?string
    {
        return $this->request->getParam('website');
    }

    private function getStoreParam(): ?string
    {
        return $this->request->getParam('store');
    }
}
