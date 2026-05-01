<?php

declare(strict_types=1);

namespace AvS\ScopeHint\Service;

use AvS\ScopeHint\Api\Data\OverrideResultInterface;
use AvS\ScopeHint\Api\OverrideSourceInterface;

/**
 * Resolves the first matching OverrideSourceInterface for a given UI form field.
 *
 * Sources are registered via DI argument injection — the same pattern used by
 * Service\ValueFormatter\Pool. Priority is determined by the array order in
 * di.xml (lowest sortOrder wins).
 */
class OverrideSourcePool
{
    /** @var OverrideSourceInterface[] */
    private $sources;

    /**
     * @param OverrideSourceInterface[] $sources
     */
    public function __construct(array $sources = [])
    {
        $this->sources = $sources;
    }

    /**
     * Find the first source that supports the given entity type + field meta,
     * then delegate to it. Returns null when no source matches (soft/silent).
     *
     * @param string $entityType Either "product" or "category".
     * @param int    $entityId
     * @param array  $fieldMeta  The `arguments.data.config` slice of the field's UI meta.
     * @return OverrideResultInterface|null
     */
    public function resolve(string $entityType, int $entityId, array $fieldMeta): ?OverrideResultInterface
    {
        foreach ($this->sources as $source) {
            if ($source->supports($entityType, $fieldMeta)) {
                return $source->getOverrides($entityType, $entityId, $fieldMeta);
            }
        }

        return null;
    }
}
