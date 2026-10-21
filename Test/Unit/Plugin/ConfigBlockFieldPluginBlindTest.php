<?php

declare(strict_types=1);

namespace AvS\ScopeHint\Test\Unit\Plugin;

use AvS\ScopeHint\Model\Data\OverrideResult;
use AvS\ScopeHint\Plugin\ConfigBlockFieldPlugin;
use AvS\ScopeHint\Service\ScopeOverrideDetectorInterface;
use Magento\Config\Block\System\Config\Form\Field as FieldBlock;
use Magento\Config\Model\Config\Structure;
use Magento\Config\Model\Config\Structure\Element\Field;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\View\LayoutInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ConfigBlockFieldPluginBlindTest extends TestCase
{
    private const LEGACY_FLAG = 'dev/debug/scopehint_legacy_tooltip';

    /** @var ScopeOverrideDetectorInterface&MockObject */
    private $detector;

    /** @var LayoutInterface&MockObject */
    private $layout;

    /** @var ScopeConfigInterface&MockObject */
    private $scopeConfig;

    /** @var Structure&MockObject */
    private $structure;

    /** @var ConfigBlockFieldPlugin */
    private $plugin;

    protected function setUp(): void
    {
        $this->detector = $this->createMock(ScopeOverrideDetectorInterface::class);
        $this->layout = $this->createMock(LayoutInterface::class);
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->structure = $this->createMock(Structure::class);

        $this->plugin = new ConfigBlockFieldPlugin(
            $this->detector,
            $this->layout,
            $this->scopeConfig,
            $this->structure
        );
    }

    private function createElement(array $fieldConfig): AbstractElement
    {
        $element = $this->getMockBuilder(AbstractElement::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getHtmlId'])
            ->getMockForAbstractClass();
        $element->method('getHtmlId')->willReturn('test_html_id');
        $element->setData('field_config', $fieldConfig);
        $element->setData('after_element_html', '<span>original</span>');

        return $element;
    }

    private function createField(?string $configPath, string $path): Field
    {
        $field = $this->createMock(Field::class);
        $field->method('getConfigPath')->willReturn($configPath);
        $field->method('getPath')->willReturn($path);

        return $field;
    }

    public function testC1_1UsesStructureFieldConfigPath(): void
    {
        $field = $this->createField('payment/free/title', 'payment_us/free/title');
        $this->structure->method('getElement')->with('payment_us/free/title')->willReturn($field);
        $this->detector->expects(self::once())
            ->method('getOverrides')
            ->with('payment/free/title', $field)
            ->willReturn(new OverrideResult([]));

        $this->plugin->beforeRender(
            $this->createMock(FieldBlock::class),
            $this->createElement(['path' => 'payment_us/free', 'id' => 'title'])
        );
    }

    public function testC1_2FallsBackToStructurePathWhenConfigPathIsNull(): void
    {
        $field = $this->createField(null, 'payment_us/free/title');
        $this->structure->method('getElement')->with('payment_us/free/title')->willReturn($field);
        $this->detector->expects(self::once())
            ->method('getOverrides')
            ->with('payment_us/free/title', $field)
            ->willReturn(new OverrideResult([]));

        $this->plugin->beforeRender(
            $this->createMock(FieldBlock::class),
            $this->createElement(['path' => 'payment_us/free', 'id' => 'title'])
        );
    }

    public function testC1_3ExplicitConfigPathLookupUsesFieldConfigPath(): void
    {
        $field = $this->createField('custom/stored/path', 'section/group/field');
        $this->structure->method('getElement')->with('section/group/field')->willReturn(null);
        $this->structure->method('getElementByConfigPath')
            ->with('custom/stored/path')
            ->willReturn($field);
        $this->detector->expects(self::once())
            ->method('getOverrides')
            ->with('custom/stored/path', $field)
            ->willReturn(new OverrideResult([]));

        $this->plugin->beforeRender(
            $this->createMock(FieldBlock::class),
            $this->createElement([
                'path' => 'section/group',
                'id' => 'field',
                'config_path' => 'custom/stored/path',
            ])
        );
    }

    public function testC1_4NoFieldFoundSkipsDetectorAndKeepsHtml(): void
    {
        $this->structure->method('getElement')->willReturn(null);
        $this->structure->method('getElementByConfigPath')->willReturn(null);
        $this->detector->expects(self::never())->method('getOverrides');

        $element = $this->createElement([
            'path' => 'section/group',
            'id' => 'field',
            'config_path' => 'custom/stored/path',
        ]);

        $this->plugin->beforeRender($this->createMock(FieldBlock::class), $element);

        self::assertSame('<span>original</span>', $element->getData('after_element_html'));
    }

    public function testC1_5LegacyTooltipFlagSkipsDetector(): void
    {
        $this->scopeConfig->method('isSetFlag')->with(self::LEGACY_FLAG)->willReturn(true);
        $field = $this->createField('payment/free/title', 'payment_us/free/title');
        $this->structure->method('getElement')->willReturn($field);
        $this->detector->expects(self::never())->method('getOverrides');

        $this->plugin->beforeRender(
            $this->createMock(FieldBlock::class),
            $this->createElement(['path' => 'payment_us/free', 'id' => 'title'])
        );
    }
}
