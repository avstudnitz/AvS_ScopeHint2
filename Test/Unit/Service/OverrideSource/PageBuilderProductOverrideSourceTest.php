<?php

declare(strict_types=1);

namespace AvS\ScopeHint\Test\Unit\Service\OverrideSource;

use AvS\ScopeHint\Api\Data\ScopeOverrideInterface;
use AvS\ScopeHint\Model\Data\OverrideResult;
use AvS\ScopeHint\Model\Data\ScopeOverride;
use AvS\ScopeHint\Service\EavValueFormatter\PageBuilderFormatter;
use AvS\ScopeHint\Service\OverrideSource\EavProductOverrideSource;
use AvS\ScopeHint\Service\OverrideSource\PageBuilderProductOverrideSource;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PageBuilderProductOverrideSourceTest extends TestCase
{
    /** @var EavProductOverrideSource&MockObject */
    private $eavProductSource;

    /** @var PageBuilderFormatter&MockObject */
    private $formatter;

    /** @var PageBuilderProductOverrideSource */
    private $source;

    protected function setUp(): void
    {
        $this->eavProductSource = $this->createMock(EavProductOverrideSource::class);
        $this->formatter = $this->createMock(PageBuilderFormatter::class);
        $this->source = new PageBuilderProductOverrideSource($this->eavProductSource, $this->formatter);
    }

    public function testSupportsPageBuilderProductField(): void
    {
        $fieldMeta = $this->pageBuilderMeta();
        $this->eavProductSource->expects(self::once())->method('supports')
            ->with('product', $fieldMeta)->willReturn(true);
        $this->formatter->expects(self::once())->method('supports')->with($fieldMeta)->willReturn(true);

        self::assertTrue($this->source->supports('product', $fieldMeta));
    }

    public function testDoesNotInspectPageBuilderMetadataWhenGenericEavFieldIsUnsupported(): void
    {
        $fieldMeta = $this->pageBuilderMeta();
        $this->eavProductSource->method('supports')->willReturn(false);
        $this->formatter->expects(self::never())->method('supports');

        self::assertFalse($this->source->supports('category', $fieldMeta));
    }

    public function testDoesNotSupportNormalProductEavField(): void
    {
        $fieldMeta = ['code' => 'name', 'globalScope' => false];
        $this->eavProductSource->method('supports')->willReturn(true);
        $this->formatter->method('supports')->with($fieldMeta)->willReturn(false);

        self::assertFalse($this->source->supports('product', $fieldMeta));
    }

    public function testDelegatesDetectionAndPreservesScopeMetadataAndRawValues(): void
    {
        $fieldMeta = $this->pageBuilderMeta();
        $germanRaw = '<div data-content-type="text"><p>German content</p></div>';
        $frenchRaw = '<div data-content-type="text"><p>French content</p></div>';
        $result = new OverrideResult([
            new ScopeOverride(
                ScopeOverrideInterface::SCOPE_TYPE_STORES,
                'de',
                'German Store',
                2,
                'base',
                $germanRaw,
                'unformatted German value'
            ),
            new ScopeOverride(
                ScopeOverrideInterface::SCOPE_TYPE_STORES,
                'fr',
                'French Store',
                3,
                'eu',
                $frenchRaw,
                'unformatted French value'
            ),
        ]);

        $this->eavProductSource->expects(self::once())->method('getOverrides')
            ->with('product', 42, $fieldMeta)->willReturn($result);
        $this->formatter->method('format')->willReturnMap([
            [$germanRaw, 'German content (Page Builder content differs)'],
            [$frenchRaw, 'French content (Page Builder content differs)'],
        ]);

        $formatted = $this->source->getOverrides('product', 42, $fieldMeta)->getOverrides();

        self::assertCount(2, $formatted);
        self::assertSame('stores', $formatted[0]->getScopeType());
        self::assertSame('de', $formatted[0]->getScopeCode());
        self::assertSame('German Store', $formatted[0]->getScopeName());
        self::assertSame(2, $formatted[0]->getScopeId());
        self::assertSame('base', $formatted[0]->getWebsiteCode());
        self::assertSame($germanRaw, $formatted[0]->getRawValue());
        self::assertSame('German content (Page Builder content differs)', $formatted[0]->getDisplayValue());
        self::assertSame($frenchRaw, $formatted[1]->getRawValue());
        self::assertSame('French content (Page Builder content differs)', $formatted[1]->getDisplayValue());
    }

    public function testReturnsEmptyResultWhenGenericSourceFindsNoOverrides(): void
    {
        $this->eavProductSource->method('getOverrides')->willReturn(new OverrideResult([]));
        $this->formatter->expects(self::never())->method('format');

        self::assertTrue(
            $this->source->getOverrides('product', 42, $this->pageBuilderMeta())->isEmpty()
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function pageBuilderMeta(): array
    {
        return [
            'code' => 'description',
            'globalScope' => false,
            'formElement' => 'wysiwyg',
            'wysiwygConfigData' => ['is_pagebuilder_enabled' => true],
        ];
    }
}
