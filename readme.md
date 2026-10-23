AvS_ScopeHint for Magento 2
=====================
Displays a hint when a configuration value is overwritten on a lower scope (website or store view).

Facts
-----
- [extension on GitHub](https://github.com/avstudnitz/AvS_ScopeHint2)
- [direct download link](https://github.com/avstudnitz/AvS_ScopeHint2/archive/master.tar.gz)
- Composer key: `avstudnitz/scopehint2` (registered at Packagist)

Description
-----------
Whenever a value is overwritten on a lower scope (website or store view), a badge with the number of overrides is displayed next to the field.
Clicking the badge opens an accessible popover that lists the overridden values grouped by website, with a filter for long lists.

Scope hints are shown in these areas of the Magento admin:

- **System configuration**: all field types, including select and multiselect fields (shown with their option labels), serialized field arrays (listing the rows that differ) and editor fields.
- **Product edit form**: all store view and website scoped attributes, including Page Builder attributes, which are shown as a short text preview.
- **Category edit form**: all store view scoped attributes.
- **Product grid**: overridden values are marked with a badge in the grid cells, e.g. for name, status, visibility and any attribute column added to the grid.

When a product or category is edited on store view scope, the hints compare against the value of that store view, and the store view itself is not listed.

![System configuration](docs/images/system-config-popover.png?raw=true "Scope hint in the system configuration")

![Product edit form](docs/images/product-form-popover.png?raw=true "Scope hint on a Page Builder attribute in the product edit form")

![Product grid](docs/images/product-grid-pills.png?raw=true "Scope hint in the product grid")

The module also optionally displays the configuration path (which is used for `ScopeConfigInterface::getValue()`) with the configuration fields, and the attribute code with the product attributes.
Both are on by default; see [Configuration](#configuration).

Requirements
------------
- PHP 8.1 through 8.5

Compatibility
-------------
- Magento 2.4.x (Open Source and Commerce)
- Compatible with the default backend theme, the Mage-OS M137 admin theme, and the Hyvä commerce admin theme

Installation Instructions
-------------------------

### Via Composer:

1. Call `composer require avstudnitz/scopehint2` in your Magento root dir from the command line

### Via Download

1. Copy all the files into the newly created directory 
`app/code/AvS/ScopeHint/` in the Magento 2 root.

### In both cases:

2. Enable the extension by calling `bin/magento module:enable AvS_ScopeHint`.
3. Run `bin/magento setup:upgrade`.

Configuration
-------------
All settings are located in
**Stores → Settings → Configuration → Advanced → Developer → Debug**:

| Setting | Config path | Default |
|---|---|---|
| Display path for each option in system configuration | `dev/debug/show_path_in_adminhtml` | Yes |
| Display attribute code for each field in the product edit | `dev/debug/show_attribute_code_in_adminhtml` | Yes |
| Use legacy scopehint tooltip (deprecated) | `dev/debug/scopehint_legacy_tooltip` | No |
| Maximum stores for scope hint detection | `dev/debug/scopehint_max_stores` | 50 |

Uninstallation
--------------
1. Uninstall the extension by calling `bin/magento module:uninstall AvS_ScopeHint`.
2. Remove all extension files from `app/code/AvS/ScopeHint/` or use Composer to remove the extension if you have installed it with Composer

Support
-------
If you have any issues with this extension, open an issue on [GitHub](https://github.com/avstudnitz/AvS_ScopeHint2/issues).

Contribution
------------
Any contribution is highly appreciated. The best way to contribute code is to open a [pull request on GitHub](https://docs.github.com/en/pull-requests/collaborating-with-pull-requests/proposing-changes-to-your-work-with-pull-requests/about-pull-requests).

Extending
---------
Other modules can add scope hints for their own product or category form fields by registering an override source.
See [docs/extending.md](docs/extending.md) for details and an example.

Developers
----------
Andreas von Studnitz

[@avstudnitz](https://www.linkedin.com/in/andreasv1/)

Andrea D'Urso

[@adexandros](https://github.com/adexandros)

Licence
-------
[OSL - Open Software Licence 3.0](https://opensource.org/licenses/osl-3.0.php)

Performance
-----------
System configuration overrides are read from `core_config_data` with a single query per request.

On the product and category edit forms, the module loads the entity once per store view to check for overridden attribute values.
On setups with many store views this can cause a noticeable load.
To prevent this, detection is skipped, and a warning is written to the Magento system log, when the total number of store views exceeds a configurable threshold
(config path `dev/debug/scopehint_max_stores`, default **50**, see [Configuration](#configuration)).

The product grid reads the overrides of all displayed products with one query per attribute type, limited to the attributes shown in the grid.

Copyright
---------
(c) 2017-2026 Andreas von Studnitz
