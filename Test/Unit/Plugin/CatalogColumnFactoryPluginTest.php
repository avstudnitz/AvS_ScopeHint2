<?php

declare(strict_types=1);

namespace AvS\ScopeHint\Test\Unit\Plugin;

use AvS\ScopeHint\Plugin\CatalogColumnFactoryPlugin;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute as CatalogAttribute;
use Magento\Catalog\Ui\Component\ColumnFactory;
use Magento\Eav\Model\Entity\Attribute\AbstractAttribute;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Framework\DataObject;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CatalogColumnFactoryPluginTest extends TestCase
{
    /** @var CatalogColumnFactoryPlugin */
    private $plugin;

    /** @var ColumnFactory&MockObject */
    private $subject;

    protected function setUp(): void
    {
        $this->plugin  = new CatalogColumnFactoryPlugin();
        $this->subject = $this->createMock(ColumnFactory::class);
    }

    public function testStoreScoped_textAttribute_setsBodyTmpl(): void
    {
        $attribute = $this->buildAttribute(ScopedAttributeInterface::SCOPE_STORE, 'text');
        $column    = $this->buildColumn([]);

        $result = $this->plugin->afterCreate($this->subject, $column, $attribute, null);

        $config = $result->getData('config');
        $this->assertSame('AvS_ScopeHint/ui/grid/cells/scopehint', $config['bodyTmpl']);
    }

    public function testWebsiteScoped_textareaAttribute_setsBodyTmpl(): void
    {
        $attribute = $this->buildAttribute(ScopedAttributeInterface::SCOPE_WEBSITE, 'textarea');
        $column    = $this->buildColumn([]);

        $result = $this->plugin->afterCreate($this->subject, $column, $attribute, null);

        $config = $result->getData('config');
        $this->assertSame('AvS_ScopeHint/ui/grid/cells/scopehint', $config['bodyTmpl']);
    }

    public function testGlobalScope_doesNotSetBodyTmpl(): void
    {
        $attribute = $this->buildAttribute(ScopedAttributeInterface::SCOPE_GLOBAL, 'text');
        $column    = $this->buildColumn([]);

        $result = $this->plugin->afterCreate($this->subject, $column, $attribute, null);

        $config = $result->getData('config');
        $this->assertArrayNotHasKey('bodyTmpl', $config);
    }

    public function testImageFrontendInput_doesNotSetBodyTmpl(): void
    {
        $attribute = $this->buildAttribute(ScopedAttributeInterface::SCOPE_STORE, 'image');
        $column    = $this->buildColumn([]);

        $result = $this->plugin->afterCreate($this->subject, $column, $attribute, null);

        $config = $result->getData('config');
        $this->assertArrayNotHasKey('bodyTmpl', $config);
    }

    public function testMediaImageFrontendInput_doesNotSetBodyTmpl(): void
    {
        $attribute = $this->buildAttribute(ScopedAttributeInterface::SCOPE_STORE, 'media_image');
        $column    = $this->buildColumn([]);

        $result = $this->plugin->afterCreate($this->subject, $column, $attribute, null);

        $config = $result->getData('config');
        $this->assertArrayNotHasKey('bodyTmpl', $config);
    }

    public function testNonAbstractAttribute_returnsColumnUnchanged(): void
    {
        $column = $this->buildColumn(['someKey' => 'someValue']);

        $result = $this->plugin->afterCreate($this->subject, $column, new \stdClass(), null);

        $config = $result->getData('config');
        $this->assertArrayNotHasKey('bodyTmpl', $config);
        $this->assertSame('someValue', $config['someKey']);
    }

    public function testExistingConfigKeysPreservedWhenBodyTmplSet(): void
    {
        $attribute = $this->buildAttribute(ScopedAttributeInterface::SCOPE_STORE, 'text');
        $column    = $this->buildColumn(['label' => 'My Label', 'visible' => true]);

        $result = $this->plugin->afterCreate($this->subject, $column, $attribute, null);

        $config = $result->getData('config');
        $this->assertSame('AvS_ScopeHint/ui/grid/cells/scopehint', $config['bodyTmpl']);
        $this->assertSame('My Label', $config['label']);
        $this->assertTrue($config['visible']);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * @param int $scope
     * @param string $frontendInput
     * @return CatalogAttribute&MockObject
     */
    private function buildAttribute(int $scope, string $frontendInput): CatalogAttribute
    {
        $attribute = $this->createMock(CatalogAttribute::class);
        $attribute->method('getIsGlobal')->willReturn((string) $scope);
        $attribute->method('getFrontendInput')->willReturn($frontendInput);

        return $attribute;
    }

    /**
     * @param array $config
     * @return DataObject
     */
    private function buildColumn(array $config): DataObject
    {
        $column = new DataObject();
        $column->setData('config', $config);

        return $column;
    }
}
