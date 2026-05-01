<?php

declare(strict_types=1);

namespace AvS\ScopeHint\Plugin;

use AvS\ScopeHint\Service\EavScopeOverrideDetector;
use Magento\Catalog\Model\Category\DataProvider;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute as EavAttribute;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Stdlib\ArrayManager;
use Magento\Store\Model\Store;

class CategoryDataProviderPlugin
{
    /** @var EavScopeOverrideDetector */
    private $detector;

    /** @var EavConfig */
    private $eavConfig;

    /** @var RequestInterface */
    private $request;

    /** @var ArrayManager */
    private $arrayManager;

    public function __construct(
        EavScopeOverrideDetector $detector,
        EavConfig $eavConfig,
        RequestInterface $request,
        ArrayManager $arrayManager
    ) {
        $this->detector = $detector;
        $this->eavConfig = $eavConfig;
        $this->request = $request;
        $this->arrayManager = $arrayManager;
    }

    /**
     * Inject scope-override tooltips into the category form meta.
     *
     * @param DataProvider $subject
     * @param array $result
     * @return array
     */
    public function afterGetMeta(DataProvider $subject, array $result): array
    {
        $category = $subject->getCurrentCategory();

        if ($category === null || !$category->getId()) {
            return $result;
        }

        $categoryId = (int) $category->getId();
        $storeId = $this->getCurrentStoreId();

        // Only show overrides when editing in a non-default scope.
        if ($storeId === Store::DEFAULT_STORE_ID) {
            return $result;
        }

        try {
            $attributes = $this->eavConfig->getEntityType('catalog_category')->getAttributeCollection();
        } catch (\Throwable $e) {
            return $result;
        }

        /** @var EavAttribute $attribute */
        foreach ($attributes as $attribute) {
            if ($attribute->isScopeGlobal()) {
                continue;
            }

            $attributeCode = $attribute->getAttributeCode();
            $attributePath = $this->arrayManager->findPath($attributeCode, $result);

            if ($attributePath === null) {
                continue;
            }

            $overrides = $this->detector->getCategoryOverrides($categoryId, $attribute, $storeId);

            if ($overrides->isEmpty()) {
                continue;
            }

            $lines = [];

            foreach ($overrides->getOverrides() as $override) {
                $lines[] = $override->getScopeName() . ': ' . $override->getDisplayValue();
            }

            $tooltipDescription = implode('<br>', $lines);

            $result = $this->arrayManager->merge(
                $attributePath . '/arguments/data/config',
                $result,
                ['tooltip' => ['description' => $tooltipDescription]]
            );
        }

        return $result;
    }

    private function getCurrentStoreId(): int
    {
        $storeParam = $this->request->getParam('store', Store::DEFAULT_STORE_ID);

        return (int) $storeParam;
    }
}
