<?php

namespace AvS\ScopeHint\Plugin;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Ui\DataProvider\Product\Form\Modifier\Eav;
use Magento\Framework\Registry;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Catalog\Model\Attribute\ScopeOverriddenValue;

class ProductEavDataProviderPlugin
{
    /** @var StoreManagerInterface */
    private $storeManager;

    /** @var Registry */
    private $registry;

    /** @var ProductRepositoryInterface */
    private $productRepository;

    /** @var array */
    private $stores;

    /**
     * ProductEavDataProviderPlugin constructor.
     *
     * @param StoreManagerInterface $storeManager
     * @param Registry $registry
     * @param ProductRepositoryInterface $productRepository
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        Registry $registry,
        ProductRepositoryInterface $productRepository
    ) {
        $this->storeManager = $storeManager;
        $this->registry = $registry;
        $this->productRepository = $productRepository;
    }

    /**
     * @param Eav $subject
     * @param $result
     * @return mixed
     */
    public function afterSetupAttributeMeta(Eav $subject, $result)
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

    /**
     * @param int $productId
     * @param int $storeViewId
     * @return mixed
     */
    private function getProductInStoreView($productId, $storeViewId)
    {
        return $this->productRepository->getById($productId, false, $storeViewId);
    }

    /**
     * @return array
     */
    private function getStores()
    {
        if (!$this->stores) {
            $this->stores = $this->storeManager->getStores();
        }

        return $this->stores;
    }
}
