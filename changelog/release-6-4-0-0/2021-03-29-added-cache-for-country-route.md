---
title: Added cache for country route
issue: NEXT-14094
author: OliverSkroblin
author_email: o.skroblin@shopwell.com 
author_github: OliverSkroblin
---
# Core
* Added `\Shopwell\Core\System\Country\SalesChannel\CachedCountryRoute`, which adds a cache for the store api country route
* Added `\Shopwell\Core\System\Salutation\SalesChannel\CachedSalutationRoute`, which adds a cache for the store api salutation route
* Added `Request $request` parameter to `\Shopwell\Core\System\Country\SalesChannel\AbstractCountryRoute::load`
* Added `\Shopwell\Core\Content\Product\SalesChannel\Review\CachedProductReviewRoute`, which adds a cache for the store api product review route
* Added `\Shopwell\Core\Content\Sitemap\SalesChannel\CachedSitemapRoute`, which adds a cache for the store api sitemap route
* Added `\Shopwell\Core\Content\Sitemap\Event\SitemapGeneratedEvent`, which is dispatched when a sitemap was generated
