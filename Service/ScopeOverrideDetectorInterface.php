<?php

declare(strict_types=1);

namespace AvS\ScopeHint\Service;

use AvS\ScopeHint\Api\Data\OverrideResultInterface;
use Magento\Config\Model\Config\Structure\Element\Field as Subject;

/**
 * Detects scope-level overrides for a single configuration path.
 *
 * @api
 */
interface ScopeOverrideDetectorInterface
{
    /**
     * Returns all websites/stores whose value at the given path differs from the
     * current scope's value. The current scope is inferred from the request
     * (`?website=` / `?store=` params) when $field is not provided, or from $field
     * directly when supplied.
     *
     * @param string $path Configuration path (e.g. "general/locale/code")
     * @param Subject|null $field Optional config-structure field; used for option-label resolution
     */
    public function getOverrides(string $path, ?Subject $field = null): OverrideResultInterface;
}
