<?php

namespace AvS\ScopeHint\Plugin;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Framework\Escaper;
use Magento\Framework\Registry;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Ui\Component\Form\Field as FieldComponent;

class CategoryFieldPlugin
{
    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var Registry
     */
    protected $registry;

    /**
     * @var CategoryRepositoryInterface
     */
    protected $categoryRepository;

    /**
     * @var Escaper
     */
    protected $escaper;

    /**
     * @param StoreManagerInterface $storeManager
     * @param Registry $registry
     * @param CategoryRepositoryInterface $categoryRepository
     * @param Escaper $escaper
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        Registry $registry,
        CategoryRepositoryInterface $categoryRepository,
        Escaper $escaper
    ) {
        $this->storeManager = $storeManager;
        $this->registry = $registry;
        $this->categoryRepository = $categoryRepository;
        $this->escaper = $escaper;
    }

    /**
     * @param FieldComponent $subject
     * @param $config
     * @return array
     */
    public function afterGetConfiguration(FieldComponent $subject, $config)
    {
        if ($subject->getContext()->getNamespace() == 'category_form' &&
            $config['formElement'] != 'hidden' &&
            strpos($config['dataScope'], 'use_config.') === false
        ) {
            $scopeHints = [];
            $attributeCode = $config['dataScope'];
            $storeViews = $this->storeManager->getStores();
            $category = $this->registry->registry('current_category');

            if ($category === null || $category->getId() === null) {
                return $config;
            }

            foreach ($storeViews as $storeView) {
                $categoryByStoreCode = $this->getCategoryInStoreView($category->getId(), $storeView->getId());
                $currentScopeValueForCode = $value = $categoryByStoreCode->getData($attributeCode);

                if ($config['dataScope'] == 'select' && !is_array($currentScopeValueForCode)) {
                    $value = $categoryByStoreCode
                        ->getResource()
                        ->getAttribute($attributeCode)
                        ->getSource()
                        ->getOptionText($currentScopeValueForCode);
                }

                try {
                    $valueAsString = (string) $value;
                } catch (\Throwable $ex) {
                    $valueAsString = json_encode($value);
                    if ($valueAsString === false) {
                        $valueAsString = null;
                    }
                }

                if ($valueAsString !== null && $category->getData($attributeCode) !== $currentScopeValueForCode) {
                    $scopeHints[] = '<code>' . $storeView->getName() . '</code> : ' . $this->escaper->escapeHtml($valueAsString);
                }

                if (!empty($scopeHints)) {
                    $config['tooltip']['description'] = implode('<br>', $scopeHints);
                }
            }
        }
        return $config;
    }

    /**
     * @param $categoryId
     * @param $storeViewId
     * @return \Magento\Catalog\Api\Data\CategoryInterface
     */
    private function getCategoryInStoreView($categoryId, $storeViewId)
    {
        return $this->categoryRepository->get($categoryId, $storeViewId);
    }
}
