<?php
namespace AvS\ScopeHint\Plugin;

use Magento\Config\Model\Config\Structure\Element\Field as Subject;

class ConfigFieldPlugin
{
    /**
     * @param Subject $subject
     * @param string $result
     * @return string
     */
    public function afterGetTooltip(Subject $subject, $result)
    {
        return $result;
    }

    /**
     * @param Subject $subject
     * @param string $result
     * @return string
     */
    public function afterGetComment(Subject $subject, $result)
    {
        if (strlen(trim($result))) {
            $result .= '<br />';
        }
        $result .= __('Path: <code>%1</code>', $subject->getPath());
        return $result;
    }
}