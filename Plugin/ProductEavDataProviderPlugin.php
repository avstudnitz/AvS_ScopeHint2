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

    protected $registry;

    protected $productRepository;

    protected $stores;

    public function __construct(
        StoreManagerInterface $storeManager,
        ScopeOverriddenValue $scopeOverriddenValue,
        \Magento\Framework\Registry $registry,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository
    ) {
        $this->storeManager = $storeManager;
        $this->scopeOverriddenValue = $scopeOverriddenValue;
        $this->registry = $registry;
        $this->productRepository = $productRepository;
    }

    public function afterSetupAttributeMeta(\Magento\Catalog\Ui\DataProvider\Product\Form\Modifier\Eav $subject, $result)
    {
        if (!isset($result['arguments']['data']['config']['code'])
            || $result['arguments']['data']['config']['globalScope']
            || $result['arguments']['data']['config']['code'] == 'quantity_and_stock_status'
        ) {
            return $result;
        }

        $scopeHints = [];
        $attributeCode = $result['arguments']['data']['config']['code'];
        $storeViews = $this->getStores();
        $product = $this->registry->registry('current_product');

        foreach ($storeViews as $storeView) {
            $productByStoreCode = $this->getProductInStoreView($product->getId(), $storeView->getId());
            $currentScopeValueForCode = $value = $productByStoreCode->getData($attributeCode);

            if ($result['arguments']['data']['config']['dataType'] == 'select'
                && !is_array($currentScopeValueForCode)
            ) {
                $value = $productByStoreCode->getResource()->getAttribute($attributeCode)->getSource()->getOptionText($currentScopeValueForCode);
            }

            if ($product->getData($attributeCode) !== $currentScopeValueForCode) {
                $scopeHints[] = $storeView->getName() . ': ' . $value;
            }

            if (!empty($scopeHints)) {
                $result['arguments']['data']['config']['tooltip']['description'] = implode('<br>', $scopeHints);
            }
        }

        return $result;
    }

    private function getProductInStoreView($productId, $storeViewId)
    {
        return $this->productRepository->getById($productId, false, $storeViewId);
    }

    private function getStores()
    {
        if (!$this->stores) {
            $this->stores = $this->storeManager->getStores();
        }

        return $this->stores;
    }
}
