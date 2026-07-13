# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

## [Unreleased]

### Added

- `Api\OverrideSourceInterface` — extension point for UI-form scope-override
  detection. Third-party modules can register custom sources via
  `AvS\ScopeHint\Service\OverrideSourcePool` in `etc/adminhtml/di.xml`.
  See `docs/extending.md` for a worked example.
- `Service\OverrideSourcePool` — first-match-wins dispatcher. Sources are
  prioritised by their order in the DI `sources` array.
- `Service\OverrideSource\EavProductOverrideSource` — default source covering
  real Magento EAV product attributes (extracted from
  `ProductEavDataProviderPlugin`).
- `Service\OverrideSource\EavCategoryOverrideSource` — default source covering
  real Magento EAV category attributes.
- `Block\Adminhtml\Eav\Popover` + `view/adminhtml/templates/eav/popover.phtml`
  — interactive popover block for EAV UI form fields (mirrors the system-config
  popover block from PR 5).
- `view/adminhtml/web/js/popover-bootstrap.js` — RequireJS bootstrap that
  re-binds `[data-scopehint-popover]` nodes after Knockout injects HTML into the
  DOM (KO `html=` does not trigger `mage/apply` automatically).
- Scope-aware product-grid cells that preserve Magento's native rendering and
  add a separately escaped override popover.
- Modern system-configuration popovers rendered next to each input, including
  support for Magento Backend and Hyvä Commerce admin themes.

### Changed

- `Plugin\ProductEavDataProviderPlugin` — slim; delegates override detection to
  `OverrideSourcePool`, renders popover into the field's `additionalInfo` meta
  slot. Legacy `<br>`-list tooltip in `tooltip.description` preserved behind the
  `dev/debug/scopehint_legacy_tooltip` config flag.
- `Plugin\CategoryDataProviderPlugin` — refactored to the same shape as
  `ProductEavDataProviderPlugin`. Iterates the meta tree, delegates to
  `OverrideSourcePool`, writes popover HTML to `additionalInfo`. Legacy tooltip
  mode preserved via the same flag.
- System configuration detection now reads explicit `core_config_data` rows,
  so inherited website values are not misreported as store-view overrides.
- Product grid aggregation uses Magento's metadata link field and therefore
  supports both Open Source (`entity_id`) and Commerce (`row_id`) schemas.
- Category fields resolve their real EAV attribute scope before enabling hints.
- Datetime formatting retains the time component for `datetime` and `time`
  fields. Popovers bind after asynchronous rendering and close on Escape.

### Breaking Changes

- **`Plugin\ProductEavDataProviderPlugin` constructor changed.** Previous
  constructor accepted `StoreManagerInterface`, `Registry`, and
  `ProductRepositoryInterface`. New constructor accepts `OverrideSourcePool`,
  `LayoutInterface`, `ScopeConfigInterface`, `Registry`, and `Escaper`. These are private
  plugin classes with no public API contract; no deprecated alias is provided.
- **`Plugin\CategoryDataProviderPlugin` constructor changed.** Previous
  constructor accepted `EavScopeOverrideDetector`, `EavConfig`,
  `RequestInterface`, and `ArrayManager`. New constructor accepts
  `OverrideSourcePool`, `LayoutInterface`, `ScopeConfigInterface`,
  `ArrayManager`, and `Escaper`.

If you have overridden either plugin class via `<preference>` in your DI
configuration, update your constructor accordingly.
