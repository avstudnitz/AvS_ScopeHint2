AvS_ScopeHint
=====================
Displays a hint when a configuration value is overwritten on a lower scope (website or store view).
Works for product and category attributes too (as of v0.3.0)

Facts
-----
- version: 0.5.0
- extension key: AvS_ScopeHint
- [extension on Magento Connect](http://www.magentocommerce.com/magento-connect/scope-hint.html)
- Magento Connect 1.0 extension key: `magento-community/AvS_ScopeHint`
- Magento Connect 2.0 extension key: `http://connect20.magentocommerce.com/community/AvS_ScopeHint`
- [extension on GitHub](https://github.com/avstudnitz/AvS_ScopeHint)
- [direct download link](https://github.com/avstudnitz/AvS_ScopeHint/archive/master.tar.gz)
- Composer key: `avstudnitz/scopehint` (via FireGento repository)

Description
-----------
Whenever a configuration setting is overwritten by a lower level website or store view, an icon is displayed.
On Mouseover, a list of all stores / websites which overwrite the setting is shown with the respective values.
See the [screenshot](http://www.avs-webentwicklung.de/fileadmin/modules/AvS_ScopeHint.png) to get an overview about what the module does.

Works for category and product editing too.

Now displays the configuration code (which is used for Mage::getStoreConfig) with the configuration fields.

Requirements
------------
- PHP >= 5.2.0
- Mage_Core
- Mage_Adminhtml

Compatibility
-------------
- Magento >= 1.4

Installation Instructions
-------------------------
1. Install the extension via Magento Connect with the key shown above or copy all the files into your document root.

Uninstallation
--------------
1. Remove all extension files from your Magento installation

Support
-------
If you have any issues with this extension, open an issue on [GitHub](https://github.com/avstudnitz/AvS_ScopeHint/issues).

Contribution
------------
Any contribution is highly appreciated. The best way to contribute code is to open a [pull request on GitHub](https://help.github.com/articles/using-pull-requests).

Developer
---------
Andreas von Studnitz

[http://www.avs-webentwicklung.de](http://www.avs-webentwicklung.de)

[@avstudnitz](https://twitter.com/avstudnitz)

Licence
-------
[OSL - Open Software Licence 3.0](http://opensource.org/licenses/osl-3.0.php)

Copyright
---------
(c) 2012-2013 Andreas von Studnitz
