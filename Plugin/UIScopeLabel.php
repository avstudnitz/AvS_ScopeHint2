<?php
namespace AvS\ScopeHint\Plugin;

use Magento\Eav\Api\Data\AttributeInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Stdlib\ArrayManager;
use Magento\Catalog\Ui\DataProvider\Product\Form\Modifier\Eav as EavModifier;

class UIScopeLabel
{
    private const CONFIG_SHOW_ATTRIBUTE_CODE = 'dev/debug/show_attribute_code_in_adminhtml';

    public function __construct(
        protected ArrayManager $arrayManager,
        protected ScopeConfigInterface $scopeConfig,
    ) {
    }

    public function afterSetupAttributeMeta(EavModifier $subject, $result, $attribute)
    {
        if (!$this->scopeConfig->isSetFlag(self::CONFIG_SHOW_ATTRIBUTE_CODE)) {
            return $result;
        }

        $configPath = ltrim(EavModifier::META_CONFIG_PATH, ArrayManager::DEFAULT_PATH_DELIMITER);
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
