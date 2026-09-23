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
- `Plugin\CategoryDataProviderPlugin` — scope-override popovers on the category
  edit form, using the same `OverrideSourcePool` and legacy-tooltip flag as the
  product form.
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
- Product Page Builder attributes show scope-override popovers with concise,
  plain-text content previews instead of escaped Page Builder markup.

### Changed

- System configuration override detection loads all website/store-view rows of
  `core_config_data` once per request instead of one query per rendered field.
- Select, multiselect and `source_model` config values resolve to option labels
  through `Field::getOptions()`; serialized-array and editor formatters read the
  field definition through `Field::getAttribute()`.
- Product and category form hints compare against the value in the store view
  being edited and exclude that store view, as in earlier releases.
- Product grid pills show option labels, include store values that have no
  default-scope row, and only scan the attributes of the grid's columns. The
  static Status and Visibility columns show pills as well.
- Serialized-array (field array) config fields show the popover in their value
  cell; their renderers never output `after_element_html`.
- `Plugin\ProductEavDataProviderPlugin` — slim; delegates override detection to
  `OverrideSourcePool`, renders popover into the field's `additionalInfo` meta
  slot. Legacy `<br>`-list tooltip in `tooltip.description` preserved behind the
  `dev/debug/scopehint_legacy_tooltip` config flag.
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
- **`Plugin\ConfigFieldPlugin` public constants removed.** `SCOPE_TYPE_WEBSITES`
  and `SCOPE_TYPE_STORES` no longer exist on the plugin; use
  `Api\Data\ScopeOverrideInterface::SCOPE_TYPE_WEBSITES` / `SCOPE_TYPE_STORES`
  (same values).
- **`Plugin\ConfigFieldPlugin` constructor changed.** Previous constructor
  accepted `Escaper`, `ScopeConfigInterface`, `WebsiteRepositoryInterface`,
  `StoreRepositoryInterface`, and `RequestInterface`. New constructor accepts
  `ScopeConfigInterface` and `Service\ScopeOverrideDetectorInterface`.
- **PHP 7.4 support dropped.** The module now requires PHP 8.1 or later.

If you have overridden one of these plugin classes via `<preference>` in your DI
configuration, update your constructor accordingly.
