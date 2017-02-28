<?php
// @codingStandardsIgnoreFile

namespace AvS\ScopeHint\Test\Integration\Plugin;

class ConfigFieldPluginTest extends \Magento\TestFramework\TestCase\AbstractBackendController
{
    /**
     * @magentoDbIsolation enabled
     * @magentoAppIsolation enabled
     */
    public function testPathComment()
    {
        // Setting data for request
        $this->getRequest()
            ->setMethod('GET')
            ->setParam('section', 'general');

        $this->dispatch('backend/admin/system_config/edit');

        $this->assertContains((string)__('Path: <code>%1</code>', 'general/country/default'), $this->getResponse()->getBody());
    }
}
