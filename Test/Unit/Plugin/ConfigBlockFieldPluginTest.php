<?php

declare(strict_types=1);

namespace AvS\ScopeHint\Test\Unit\Plugin;

use AvS\ScopeHint\Api\Data\ScopeOverrideInterface;
use AvS\ScopeHint\Block\Adminhtml\System\Config\Popover;
use AvS\ScopeHint\Model\Data\OverrideResult;
use AvS\ScopeHint\Model\Data\ScopeOverride;
use AvS\ScopeHint\Plugin\ConfigBlockFieldPlugin;
use AvS\ScopeHint\Service\ScopeOverrideDetectorInterface;
use Magento\Config\Block\System\Config\Form\Field as FieldBlock;
use Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray;
use Magento\Config\Model\Config\Structure;
use Magento\Config\Model\Config\Structure\Element\Field as StructureField;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\View\LayoutInterface;
use PHPUnit\Framework\TestCase;

class ConfigBlockFieldPluginTest extends TestCase
{
    private const POPOVER_HTML = '<div class="scopehint-popover">POPOVER</div>';

    /** @var ConfigBlockFieldPlugin */
    private $plugin;

    protected function setUp(): void
    {
        $detector = $this->createMock(ScopeOverrideDetectorInterface::class);
        $detector->method('getOverrides')->willReturn(new OverrideResult([
            new ScopeOverride(ScopeOverrideInterface::SCOPE_TYPE_WEBSITES, 'base', 'Main', 1, 'base', 'x', 'x'),
        ]));

        $popover = $this->createMock(Popover::class);
        $popover->method('setOverrides')->willReturnSelf();
        $popover->method('setFieldHtmlId')->willReturnSelf();
        $popover->method('toHtml')->willReturn(self::POPOVER_HTML);
        $layout = $this->createMock(LayoutInterface::class);
        $layout->method('createBlock')->willReturn($popover);

        $field = $this->createMock(StructureField::class);
        $field->method('getPath')->willReturn('section/group/field');
        $structure = $this->createMock(Structure::class);
        $structure->method('getElement')->willReturn($field);

        $this->plugin = new ConfigBlockFieldPlugin(
            $detector,
            $layout,
            $this->createMock(ScopeConfigInterface::class),
            $structure
        );
    }

    public function testRegularFieldGetsPopoverAfterElement(): void
    {
        $element = $this->createElement();
        $subject = $this->createMock(FieldBlock::class);

        $this->plugin->beforeRender($subject, $element);

        self::assertSame(self::POPOVER_HTML, $element->getData('after_element_html'));
        self::assertSame(
            '<tr><td class="value"><input/></td></tr>',
            $this->plugin->afterRender($subject, '<tr><td class="value"><input/></td></tr>', $element)
        );
    }

    public function testFieldArrayGetsPopoverAtStartOfValueCell(): void
    {
        $element = $this->createElement();
        $subject = $this->createMock(AbstractFieldArray::class);

        $this->plugin->beforeRender($subject, $element);
        $rendered = $this->plugin->afterRender(
            $subject,
            '<tr><td class="label">L</td><td class="value with-tooltip"><table><tr><td class="value">cell</td></tr></table></td></tr>',
            $element
        );

        self::assertNull($element->getData('after_element_html'));
        self::assertSame(
            '<tr><td class="label">L</td><td class="value with-tooltip">' . self::POPOVER_HTML
            . '<table><tr><td class="value">cell</td></tr></table></td></tr>',
            $rendered
        );
    }

    public function testDetectsOverridesOnTheStructureFieldConfigPath(): void
    {
        $field = $this->createMock(StructureField::class);
        $field->method('getPath')->willReturn('payment_us/free/title');
        $field->method('getConfigPath')->willReturn('payment/free/title');
        $structure = $this->createMock(Structure::class);
        $structure->method('getElement')->with('payment_us/free/title')->willReturn($field);

        $detector = $this->createMock(ScopeOverrideDetectorInterface::class);
        $detector->expects(self::once())
            ->method('getOverrides')
            ->with('payment/free/title', $field)
            ->willReturn(new OverrideResult([]));

        $plugin = new ConfigBlockFieldPlugin(
            $detector,
            $this->createMock(LayoutInterface::class),
            $this->createMock(ScopeConfigInterface::class),
            $structure
        );
        $element = $this->createElement();
        $element->setData('field_config', ['path' => 'payment_us/free', 'id' => 'title']);

        $plugin->beforeRender($this->createMock(FieldBlock::class), $element);
    }

    private function createElement(): AbstractElement
    {
        $element = $this->getMockBuilder(AbstractElement::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getHtmlId'])
            ->getMockForAbstractClass();
        $element->method('getHtmlId')->willReturn('section_group_field');
        $element->setData('field_config', ['path' => 'section/group', 'id' => 'field']);

        return $element;
    }
}
