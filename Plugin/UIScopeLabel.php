<?php

declare(strict_types=1);

namespace AvS\ScopeHint\Plugin;

use Magento\Catalog\Api\Data\ProductAttributeInterface;
use Magento\Eav\Api\Data\AttributeInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Stdlib\ArrayManager;
use Magento\Catalog\Ui\DataProvider\Product\Form\Modifier\Eav as EavModifier;

class UIScopeLabel
{
    private const CONFIG_SHOW_ATTRIBUTE_CODE = 'dev/debug/show_attribute_code_in_adminhtml';

    /**
     * @var ArrayManager
     */
    private ArrayManager $arrayManager;

    /**
     * @var ScopeConfigInterface
     */
    private ScopeConfigInterface $scopeConfig;

    public function __construct(
        ArrayManager $arrayManager,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->arrayManager = $arrayManager;
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * @param EavModifier $subject
     * @param array $result
     * @param ProductAttributeInterface $attribute
     * @return array
     */
    public function afterSetupAttributeMeta(EavModifier $subject, $result, ProductAttributeInterface $attribute)
    {
        if (!$this->scopeConfig->isSetFlag(self::CONFIG_SHOW_ATTRIBUTE_CODE)) {
            return $result;
        }

        if ($attribute->getFrontendInput() === AttributeInterface::FRONTEND_INPUT) {
            return $result;
        }

        $configPath = ltrim(EavModifier::META_CONFIG_PATH, ArrayManager::DEFAULT_PATH_DELIMITER);
        $scopeLabel = $this->arrayManager->get(
            $configPath . ArrayManager::DEFAULT_PATH_DELIMITER . 'scopeLabel',
            $result
        );

        $scopeLabel = $attribute->getAttributeCode() . ' ' . (string)$scopeLabel;
        $result = $this->arrayManager->merge(
            $configPath,
            $result,
            ['scopeLabel' => trim($scopeLabel)]
        );

        return $result;
    }
}
