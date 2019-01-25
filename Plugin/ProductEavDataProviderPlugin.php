<?php

namespace AvS\ScopeHint\Plugin;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Ui\DataProvider\Product\Form\Modifier\Eav;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Registry;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;

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
     * @throws NoSuchEntityException
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
        $productId = $this->registry->registry('current_product')->getId();
        $product = $this->getProductInStoreView($productId, 0);

        if ($product->getId() === null) {
            return $result;
        }

        foreach ($storeViews as $storeView) {
            $productByStoreCode = $this->getProductInStoreView($productId, $storeView->getId());

            if ($product->getData($attributeCode) !== $productByStoreCode->getData($attributeCode)) {
                $scopeHints[] = sprintf(
                    '%s: %s: %s',
                    $storeView->getWebsite()->getName(),
                    $storeView->getName(),
                    $this->getAttributeValue($product, $attributeCode)
                );
            }
        }

        if (!empty($scopeHints)) {
            array_unshift($scopeHints, sprintf('%s: %s', __('Default'), $this->getAttributeValue($product, $attributeCode)));
            $result['arguments']['data']['config']['tooltip']['description'] = implode("\n ", $scopeHints);
        }

        return $result;
    }

    /**
     * @param ProductInterface $product
     * @param string $attributeCode
     * @return mixed
     */
    private function getAttributeValue(ProductInterface $product, $attributeCode)
    {
        return $product
            ->getResource()
            ->getAttribute($attributeCode)
            ->getFrontend()
            ->getValue($product)
        ;
    }


    /**
     * @param int $productId
     * @param int $storeViewId
     * @return mixed
     * @throws NoSuchEntityException
     */
    private function getProductInStoreView($productId, $storeViewId)
    {
        return $this->productRepository->getById($productId, false, $storeViewId);
    }

    /**
     * @return StoreInterface[]
     */
    private function getStores()
    {
        if (!$this->stores) {
            $this->stores = $this->storeManager->getStores();
        }

        return $this->stores;
    }
}
