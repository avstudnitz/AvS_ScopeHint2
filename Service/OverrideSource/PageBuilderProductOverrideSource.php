<?php

declare(strict_types=1);

namespace AvS\ScopeHint\Service\OverrideSource;

use AvS\ScopeHint\Api\Data\OverrideResultInterface;
use AvS\ScopeHint\Api\OverrideSourceInterface;
use AvS\ScopeHint\Model\Data\OverrideResult;
use AvS\ScopeHint\Model\Data\ScopeOverride;
use AvS\ScopeHint\Service\EavValueFormatter\PageBuilderFormatter;

/**
 * Adds readable display previews to Page Builder product EAV overrides.
 *
 * Raw override detection stays in EavProductOverrideSource. Keeping this as a
 * higher-priority source makes Page Builder support explicit while preserving
 * the generic EAV source for installations without Magento Page Builder.
 */
class PageBuilderProductOverrideSource implements OverrideSourceInterface
{
    /** @var EavProductOverrideSource */
    private $eavProductSource;

    /** @var PageBuilderFormatter */
    private $formatter;

    public function __construct(
        EavProductOverrideSource $eavProductSource,
        PageBuilderFormatter $formatter
    ) {
        $this->eavProductSource = $eavProductSource;
        $this->formatter = $formatter;
    }

    public function supports(string $entityType, array $fieldMeta): bool
    {
        return $this->eavProductSource->supports($entityType, $fieldMeta)
            && $this->formatter->supports($fieldMeta);
    }

    public function getOverrides(string $entityType, int $entityId, array $fieldMeta): OverrideResultInterface
    {
        $result = $this->eavProductSource->getOverrides($entityType, $entityId, $fieldMeta);
        $formatted = [];

        foreach ($result->getOverrides() as $override) {
            $formatted[] = new ScopeOverride(
                $override->getScopeType(),
                $override->getScopeCode(),
                $override->getScopeName(),
                $override->getScopeId(),
                $override->getWebsiteCode(),
                $override->getRawValue(),
                $this->formatter->format($override->getRawValue())
            );
        }

        return new OverrideResult($formatted);
    }
}
