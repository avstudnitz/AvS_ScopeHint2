<?php
namespace AvS\ScopeHint\Plugin;

use Magento\Eav\Api\Data\AttributeInterface;
use Magento\Framework\Stdlib\ArrayManager;
use Magento\Catalog\Ui\DataProvider\Product\Form\Modifier\Eav;

class UIScopeLabel
{
    public function __construct(
        protected ArrayManager $arrayManager,
    ) {
    }

    public function afterSetupAttributeMeta(Eav $subject, $result, $attribute)
    {
        $configPath = ltrim(Eav::META_CONFIG_PATH, ArrayManager::DEFAULT_PATH_DELIMITER);
        $scopeLabel = $this->arrayManager->get(
            $configPath . ArrayManager::DEFAULT_PATH_DELIMITER . 'scopeLabel',
            $result
        );

        if ($attribute->getFrontendInput() !== AttributeInterface::FRONTEND_INPUT) {
            $scopeLabel = $attribute->getAttributeCode() . ' ' . (string)$scopeLabel;
            $result = $this->arrayManager->merge(
                $configPath,
                $result,
                ['scopeLabel' => trim($scopeLabel)]
            );
        }

        return $result;
    }
}
