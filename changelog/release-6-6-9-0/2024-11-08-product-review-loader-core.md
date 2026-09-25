---
title: Move product review loader to core
issue: NEXT-36468
author: Benjamin Wittwer
author_email: benjamin.wittwer@a-k-f.de
author_github: akf-bw
---
# Core
* Changed `\Shopwell\Core\Content\Product\Cms\ProductDescriptionReviewsCmsElementResolver` to make use of the `\Shopwell\Core\Content\Product\SalesChannel\Review\AbstractProductReviewLoader` and removed the duplicate methods
* Changed `\Shopwell\Core\Content\Product\Cms\ProductDescriptionReviewsCmsElementResolver` to now execute the `\Shopwell\Core\Content\Product\SalesChannel\Review\ProductReviewsWidgetLoadedHook`
* Added `\Shopwell\Core\Content\Product\SalesChannel\Review\AbstractProductReviewLoader` to allow overwriting product review loading logic
* Added `\Shopwell\Core\Content\Product\SalesChannel\Review\ProductReviewLoader` based on the now deprecated `\Shopwell\Storefront\Page\Product\Review\ProductReviewLoader`
* Changed `\Shopwell\Core\Content\Product\SalesChannel\Review\ProductReviewResult` to include the `totalReviewsInCurrentLanguage` field
* Added `\Shopwell\Core\Content\Product\SalesChannel\Review\ProductReviewsWidgetLoadedHook` based on the now deprecated `\Shopwell\Storefront\Page\Product\Review\ProductReviewsWidgetLoadedHook`
* Added `\Shopwell\Core\Content\Product\SalesChannel\Review\Event\ProductReviewsLoadedEvent` based on the now deprecated `\Shopwell\Storefront\Page\Product\Review\ProductReviewsLoadedEvent`
* Added `core.listing.reviewsPerPage` to config `listing` with default value `10`
___
# Storefront
* Changed `\Shopwell\Storefront\Controller\CmsController` to use newly introduced `\Shopwell\Core\Content\Product\SalesChannel\Review\AbstractProductReviewLoader`
* Changed `\Shopwell\Storefront\Controller\ProductController` to use newly introduced `\Shopwell\Core\Content\Product\SalesChannel\Review\AbstractProductReviewLoader`
* Deprecated `\Shopwell\Storefront\Page\Product\Review\ProductReviewLoader`. Use `\Shopwell\Core\Content\Product\SalesChannel\Review\AbstractProductReviewLoader` instead
* Deprecated `\Shopwell\Storefront\Page\Product\Review\ProductReviewsLoadedEvent`. Use `\Shopwell\Core\Content\Product\SalesChannel\Review\Event\ProductReviewsLoadedEvent` instead
* Deprecated `\Shopwell\Storefront\Page\Product\Review\ProductReviewsWidgetLoadedHook`. Use `\Shopwell\Core\Content\Product\SalesChannel\Review\ProductReviewsWidgetLoadedHook` instead
* Deprecated `\Shopwell\Storefront\Page\Product\Review\ReviewLoaderResult`. Use `\Shopwell\Core\Content\Product\SalesChannel\Review\ProductReviewResult` instead
* Changed `review.html.twig` template to include the new config `core.listing.reviewsPerPage`
* Changed `review.html.twig` template to include missing `totalReviewsInCurrentLanguage` and `numberOfReviewsNotInCurrentLanguage` variables
* Added new blocks `component_review_list_action_filters` and `component_review_list_counter` to `review.html.twig`
___
# Upgrade Information

## Product review loading moved to core
The logic responsible for loading product reviews was unified and moved to the core.
* The service `\Shopwell\Storefront\Page\Product\Review\ProductReviewLoader` is deprecated. Use `\Shopwell\Core\Content\Product\SalesChannel\Review\AbstractProductReviewLoader` instead.
* The event `\Shopwell\Storefront\Page\Product\Review\ProductReviewsLoadedEvent` is deprecated. Use `\Shopwell\Core\Content\Product\SalesChannel\Review\Event\ProductReviewsLoadedEvent` instead.
* The hook `\Shopwell\Storefront\Page\Product\Review\ProductReviewsWidgetLoadedHook` is deprecated. Use `\Shopwell\Core\Content\Product\SalesChannel\Review\ProductReviewsWidgetLoadedHook` instead.
* The struct `\Shopwell\Storefront\Page\Product\Review\ReviewLoaderResult` is deprecated. Use `\Shopwell\Core\Content\Product\SalesChannel\Review\ProductReviewResult` instead.
___
# Next Major Version Changes

## Removal of deprecated product review loading logic in Storefront
* The service `\Shopwell\Storefront\Page\Product\Review\ProductReviewLoader` was removed. Use `\Shopwell\Core\Content\Product\SalesChannel\Review\AbstractProductReviewLoader` instead.
* The event `\Shopwell\Storefront\Page\Product\Review\ProductReviewsLoadedEvent` was removed. Use `\Shopwell\Core\Content\Product\SalesChannel\Review\Event\ProductReviewsLoadedEvent` instead.
* The hook `\Shopwell\Storefront\Page\Product\Review\ProductReviewsWidgetLoadedHook` was removed. Use `\Shopwell\Core\Content\Product\SalesChannel\Review\ProductReviewsWidgetLoadedHook` instead.
* The struct `\Shopwell\Storefront\Page\Product\Review\ReviewLoaderResult` was removed. Use `\Shopwell\Core\Content\Product\SalesChannel\Review\ProductReviewResult` instead.
