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
        // all storeviews
        $storeViews = $this->storeManager->getStores();
        // current productId
        $productId = $this->registry->registry('current_product')->getId();
        $productDump = $this->productRepository->getById($productId,false,9);
        // repo is getting values on storeViewId level.
        // find a way when loading the meta's to watch only loaded attribute.
        // otherwise load will be high
        // so how to grab the meta values -> uicomponent joined xml i think - > need to find out.
        var_dump($productDump->getData('short_description'));

        $defaultStoreView = '1';
        $currentScopeValueForCode = $this->productRepository->getById($productId, false, $defaultStoreView);

        foreach ($storeViews as $storeView) {

            $product = $this->getProductInStoreView($storeView->getId());
            //var_dump($product->getData());
            if (isset($result['arguments']['data']['config']['code'])) {
                if ($product->getData($result['arguments']['data']['config']['code']) !== $currentScopeValueForCode) {
                    //var_dump($product->getData('en_US'));
                    // different value
                    //$scopeHints[] = $storeView->getName() . ': ' . $product->getData($result['arguments']['data']['config']['code']);
                    //var_dump($scopeHints);
                }
            }

            if (! empty($scopeHints)) {
                if (isset($result['arguments']['data']['config']['tooltip']['description'])) {
                    $result['arguments']['data']['config']['tooltip']['description'] = implode('<br />', scopeHints);
                }
            }

            return $result;
        }
    }

    public function getProductInStoreView($storeViewId) {
        // hier de values op halen van attribute per stroeveiew

        $productId = $this->registry->registry('current_product')->getId();
        return $this->productRepository->getById($productId,false,4);
    }


}