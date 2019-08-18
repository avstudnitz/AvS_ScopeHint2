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

        if ($product->getId() === null) {
            return $result;
        }

        foreach ($storeViews as $storeView) {
            $productByStoreCode = $this->getProductInStoreView($product->getId(), $storeView->getId());
            $currentScopeValueForCode = $value = $productByStoreCode->getData($attributeCode);

            if ($result['arguments']['data']['config']['dataType'] == 'select'
                && !is_array($currentScopeValueForCode)
            ) {
                $value = $productByStoreCode->getResource()->getAttribute($attributeCode)->getSource()->getOptionText($currentScopeValueForCode);
            }

            // This checks if we can cast $value to a string
            // If this fails, we json_encode the value, so we eventually do get a string representation
            // If the json_encode fails for some reason, we just ignore it and won't output anything
            //
            // This problem can be seen in practice when:
            // - having at least 2 websites
            // - having the catalog price scope set to Website
            // - using different tier prices for a single product over multiple websites
            // (Another bug in that particular case, is that the tooltip doesn't seem to show up, so that's another thing which will need to get fixed someday)
            $valueAsString = null;
            try {
                $valueAsString = (string) $value;
            } catch (\Throwable $ex) {
                $valueAsString = json_encode($value);
                if ($valueAsString === false) {
                    $valueAsString = null;
                }
            }

            if ($valueAsString !== null && $product->getData($attributeCode) !== $currentScopeValueForCode) {
                $scopeHints[] = $storeView->getName() . ': ' . $valueAsString;
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
