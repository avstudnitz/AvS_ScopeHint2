AvS_ScopeHint for Magento 2
=====================
Displays a hint when a configuration value is overwritten on a lower scope (website or store view).

Facts
-----
- version: 1.0.0-beta.1
- [extension on GitHub](https://github.com/avstudnitz/AvS_ScopeHint2)
- [direct download link](https://github.com/avstudnitz/AvS_ScopeHint2/archive/master.tar.gz)
- Composer key: `avstudnitz/scopehint2` (registered at Packagist)

Description
-----------
Whenever a configuration setting is overwritten by a lower level website or store view, an icon is displayed.
On Mouseover, a list of all stores / websites which overwrite the setting is shown with the respective values.
See the screenshot to get an impression of what the module does:

![Screenshot](scopehint2-screenshot.png?raw=true "ScopeHint for Magento 2")

The module also displays the configuration code (which is used for `ScopeConfigInterface::getValue()`) with the configuration fields.

Requirements
------------
- PHP >= 5.6.0

Compatibility
-------------
- Magento  >= 2.1.0 (not tested on 2.0.x)

Installation Instructions
-------------------------
1. Install the extension via Composer with the key shown above or copy all the files into the newly created directory 
`app/code/AvS/ScopeHint/` in the Magento 2 root.
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
Andreas von Studnitz, integer_net

[http://www.integer-net.com](http://www.integer-net.com)

[@avstudnitz](https://twitter.com/avstudnitz)

Licence
-------
[OSL - Open Software Licence 3.0](http://opensource.org/licenses/osl-3.0.php)

Copyright
---------
(c) 2017 Andreas von Studnitz / integer_net GmbH
