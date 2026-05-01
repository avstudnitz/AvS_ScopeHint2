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
Whenever a configuration setting is overwritten by a lower level website or store view, an icon is displayed.
On Mouseover, a list of all stores / websites which overwrite the setting is shown with the respective values.
See the screenshot to get an impression of what the module does:

![Screenshot](scopehint2-screenshot.png?raw=true "ScopeHint for Magento 2")

The module also optionally displays the configuration code (which is used for `ScopeConfigInterface::getValue()`) with the configuration fields.
This is on by default, and can be enabled/disabled in
Stores -> Settings -> Configuration -> Advanced -> Developer -> Debug -> `Display path for each option in system configuration`

Requirements
------------
- PHP 7.4, 8.1, 8.2, or 8.3

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

Uninstallation
--------------
1. Uninstall the extension by calling `bin/magento module:uninstall AvS_ScopeHint`.
2. Remove all extension files from `app/code/AvS/ScopeHint/` or use Composer to remove the extension if you have installed it with Composer

Support
-------
If you have any issues with this extension, open an issue on [GitHub](https://github.com/avstudnitz/AvS_ScopeHint2/issues).

Contribution
------------
Any contribution is highly appreciated. The best way to contribute code is to open a [pull request on GitHub](https://help.github.com/articles/using-pull-requests).

Developer
---------
Andreas von Studnitz

[@avstudnitz](https://www.linkedin.com/in/andreasv1/)

Licence
-------
[OSL - Open Software Licence 3.0](http://opensource.org/licenses/osl-3.0.php)

Performance
-----------
When a category is edited at a non-default store-view scope, the module loads the category once per
store view to check for overridden attribute values. On large setups this can issue many
`CategoryRepository::get()` calls (one per store per page load of the category edit form).
To prevent excessive load, detection is automatically skipped — and a warning is written to the Magento
system log — when the total number of store views exceeds the configurable threshold.

The threshold is set under:
**Stores → Settings → Configuration → Advanced → Developer → Debug → Maximum stores for scope hint detection**
(config path `dev/debug/scopehint_max_stores`, default **50**).

Copyright
---------
(c) 2017-2024 Andreas von Studnitz
