---
title: Create DAL and migration database for search configuration
issue: NEXT-12490
---
# Core
* Added two new tables `product_search_config` and `product_search_config_field` to stored product searching config data and create the default data for them.
* Added entities, definition and collection for table `product_search_config` at `Shopwell\Core\Content\Product\Aggregate\ProductSearchConfig`.
* Added entities, definition and collection for table `product_search_config_field` at `Shopwell\Core\Content\Product\Aggregate\ProductSearchConfigField`.
* Added `ProductSearchConfigExceptionHandler` at `Shopwell\Core\Content\Product\Aggregate\ProductSearchConfig`.
* Added `ProductSearchConfigFieldExceptionHandler` at `Shopwell\Core\Content\Product\Aggregate\ProductSearchConfigField`.
* Added `DuplicateProductSearchConfigFieldException` and `DuplicateProductSearchConfigLanguageException` at `Shopwell\Core\Content\Product\Exception`.
* Added OneToOne association between `language` and `product_search_config`.
* Added OneToMany association between `customer_field` and `product_search_config_field`.
* Added new property `productSearchConfig` to `Shopwell\Core\System\Language\LanguageEntity`.
* Added new property `productSearchConfigFields` to `Shopwell\Core\System\CustomField\CustomFieldEntity`.
