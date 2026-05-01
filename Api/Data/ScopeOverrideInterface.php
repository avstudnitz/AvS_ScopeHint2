<?php

declare(strict_types=1);

namespace AvS\ScopeHint\Api\Data;

/**
 * Single scope (website or store) that overrides a configuration value.
 *
 * @api
 */
interface ScopeOverrideInterface
{
    public const SCOPE_TYPE_WEBSITES = 'websites';
    public const SCOPE_TYPE_STORES = 'stores';

    /**
     * @return self::SCOPE_TYPE_WEBSITES|self::SCOPE_TYPE_STORES
     */
    public function getScopeType(): string;

    public function getScopeCode(): string;

    public function getScopeName(): string;

    public function getScopeId(): int;

    /**
     * Website code containing this scope (the website itself for SCOPE_TYPE_WEBSITES,
     * the parent website for SCOPE_TYPE_STORES). Used for grouping in the popover.
     */
    public function getWebsiteCode(): string;

    /**
     * @return mixed Raw value as returned by ScopeConfig (string, array, null, ...)
     */
    public function getRawValue();

    /**
     * Already-escaped, human-readable value for display (label resolved, formatter
     * applied).
     */
    public function getDisplayValue(): string;
}
