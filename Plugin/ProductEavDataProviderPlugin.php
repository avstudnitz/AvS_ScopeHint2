<?php
namespace AvS\ScopeHint\Plugin;

use Magento\Store\Model\StoreManagerInterface;
use Magento\Catalog\Model\Attribute\ScopeOverriddenValue;

class ProductEavDataProviderPlugin
{
    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;
    /**
     * @var ScopeOverriddenValue
     */
    protected $scopeOverriddenValue;

    public function __construct(
        StoreManagerInterface $storeManager,
        ScopeOverriddenValue $scopeOverriddenValue
    ) {
        $this->storeManager = $storeManager;
        $this->scopeOverriddenValue = $scopeOverriddenValue;
    }

    public function afterSetupAttributeMeta(\Magento\Catalog\Ui\DataProvider\Product\Form\Modifier\Eav $subject, $result)
    {
        //$this->scopeOverriddenValue->containsValue();
        $result['arguments']['data']['config']['tooltip']['description'] = 'hier gaan we kijken welke stores andere waarden hebben';

        return $result;
    }
}