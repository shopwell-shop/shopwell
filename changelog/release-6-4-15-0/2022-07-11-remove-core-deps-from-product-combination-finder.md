---
title: Remove core dependencies from ProductCombinationFinder
issue: NEXT-21968
author: Stefan Sluiter
author_email: s.sluiter@shopwell.com
author_github: ssltg
---
# Storefront
* Deprecated `Shopwell\Storefront\Page\Product\Configurator\ProductCombinationFinder`. It will be removed in v6.5.0. Use `Shopwell\Core\Content\Product\SalesChannel\FindVariant\FindProductVariantRoute` instead.
* Deprecated `Shopwell\Storefront\Page\Product\Configurator\FoundCombination`. It will be removed in v6.5.0. Use `Shopwell\Core\Content\Product\SalesChannel\FindVariant\FoundCombination` instead.
* Deprecated `Shopwell\Storefront\Page\Product\Configurator\AvailableCombinationResult`. It will be removed in v6.5.0. Use `Shopwell\Core\Content\Product\SalesChannel\Detail\AvailableCombinationResult` instead.
* Deprecated `ProductCombinationFinder` as constructor parameter in `Shopwell\Storefront\Controller\ProductController`.
* Added `FindProductVariantRoute` as constructor parameter in `Shopwell\Storefront\Controller\ProductController`.
* Deprecated `ProductCombinationFinder` as constructor parameter in `Shopwell\Storefront\Controller\CmsController`.
* Added `FindProductVariantRoute` as constructor parameter in `Shopwell\Storefront\Controller\CmsController`.
___
# Core
* Added `Shopwell\Core\Content\Product\SalesChannel\FindVariant\AbstractFindProductVariantRoute`.
* Added `Shopwell\Core\Content\Product\SalesChannel\FindVariant\FindProductVariantRoute`. This route is used to find the matching variant for a given option combination of a product.
* Added new store-api route `/product/{productId}/find-variant`
* Added `Shopwell\Core\Content\Product\SalesChannel\FindVariant\FindProductVariantRouteResponse`.
* Added `Shopwell\Core\Content\Product\SalesChannel\FindVariant\FoundCombination`.