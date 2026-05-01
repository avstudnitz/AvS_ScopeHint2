<?php

declare(strict_types=1);

namespace AvS\ScopeHint\Api\Data;

/**
 * Collection of scope overrides for a single configuration path.
 *
 * @api
 */
interface OverrideResultInterface
{
    /**
     * Flat list of all overrides, in iteration order (websites first, then stores).
     *
     * @return ScopeOverrideInterface[]
     */
    public function getOverrides(): array;

    /**
     * Overrides grouped by website code.
     *
     * @return array<string, ScopeOverrideInterface[]>
     */
    public function getOverridesByWebsite(): array;

    public function count(): int;

    public function isEmpty(): bool;
}
