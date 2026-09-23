<?php

declare(strict_types=1);

namespace AvS\ScopeHint\Api;

use AvS\ScopeHint\Api\Data\OverrideResultInterface;

/**
 * Extension point for UI-form scope-override detection.
 *
 * Third-party modules (e.g. Hyva SeoSuite, Page Builder integrations)
 * implement this interface and register the implementation in
 * AvS\ScopeHint\Service\OverrideSourcePool via adminhtml di.xml.
 *
 * @api
 */
interface OverrideSourceInterface
{
    /**
     * Decide whether this source can detect overrides for the given UI form field.
     *
     * Implementations inspect the meta config (e.g. dataScope, formElement, code,
     * componentType, dataType, additionalClasses, or custom keys set by their own
     * modifier) and answer yes/no without touching the database.
     *
     * @param string $entityType Either "product" or "category" — extensible later.
     * @param array  $fieldMeta  The `arguments.data.config` slice of the field's UI meta.
     * @return bool
     */
    public function supports(string $entityType, array $fieldMeta): bool;

    /**
     * Return all scope-level overrides for the field.
     *
     * Called only when supports() returned true for the same arguments.
     *
     * @param string $entityType Either "product" or "category".
     * @param int    $entityId   The entity's primary key (product or category ID).
     * @param array  $fieldMeta  The `arguments.data.config` slice of the field's UI meta.
     * @param int    $storeId    Store view being edited (0 = default scope). Overrides are
     *                           relative to the value in this store, which is excluded.
     * @return OverrideResultInterface Each ScopeOverrideInterface::getDisplayValue() must be
     *                                 HTML-safe: popovers output it without further escaping.
     */
    public function getOverrides(
        string $entityType,
        int $entityId,
        array $fieldMeta,
        int $storeId = 0
    ): OverrideResultInterface;
}
