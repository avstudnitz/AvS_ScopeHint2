<?php

declare(strict_types=1);

namespace AvS\ScopeHint\Plugin;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class ConfigFormFieldPlugin
{
    /**
     * @param Field $subject
     * @param AbstractElement $element
     *
     * @return array
     */
    public function beforeRender(
        Field $subject,
        AbstractElement $element
    ): array {
        $element->setData(
            'after_element_html',
            $element->getData('after_element_html') . ($element->getData('field_config')['path_hint'] ?? '')
        );

        return [$element];
    }
}
