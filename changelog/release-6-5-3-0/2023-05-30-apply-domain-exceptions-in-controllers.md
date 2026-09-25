---
title: Apply domain exceptions in controllers
issue: NEXT-27457
---
# Core
* Added new method `\Shopwell\Core\Checkout\Cart\CartException::taxRuleNotFound`
* Added new methods `groupRequestNotFound`, `customersNotFound` in `\Shopwell\Core\Checkout\Customer\CustomerException`
* Added new methods `promotionsNotFound`, `discountsNotFound` in `\Shopwell\Core\Checkout\Promotion\PromotionException`
* Added various new methods to throw specific domain exception in `\Shopwell\Core\Framework\Api\ApiException` and apply them in `\Shopwell\Core\Framework\Api\` domain
* Added new domain exception class in `\Shopwell\Core\Content\Category\CategoryException`
* Added new domain exception class in `\Shopwell\Core\Content\Seo\SeoException`
___
# Elasticsearch
* Added new domain exception class in `\Shopwell\Elasticsearch\Admin\ElasticsearchAdminException`
