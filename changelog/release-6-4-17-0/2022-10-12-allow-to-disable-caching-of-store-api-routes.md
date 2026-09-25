---
title: Allow to disable caching of store-api-routes
issue: NEXT-23648
author: Simon Vorgers & Viktor Buzyka
author_email: s.vorgers@shopwell.com
author_github: SimonVorgers
---
# Core
* Changed `Shopwell\Core\Framework\Adapter\Cache\StoreApiRouteCacheKeyEvent` to allow to disable caching of store-api-routes
* Changed following Cached-Store-API-Routes to implement the new logic:
  * `Shopwell\Core\Checkout\Payment\SalesChannel\CachedPaymentMethodRoute` 
  * `Shopwell\Core\Checkout\Shipping\SalesChannel\CachedShippingMethodRoute` 
  * `Shopwell\Core\Content\Category\SalesChannel\CachedCategoryRoute` 
  * `Shopwell\Core\Content\Category\SalesChannel\CachedNavigationRoute` 
  * `Shopwell\Core\Content\LandingPage\SalesChannel\CachedLandingPageRoute` 
  * `Shopwell\Core\Content\Product\SalesChannel\CrossSelling\CachedProductCrossSellingRoute` 
  * `Shopwell\Core\Content\Product\SalesChannel\Detail\CachedProductDetailRoute` 
  * `Shopwell\Core\Content\Product\SalesChannel\Listing\CachedProductListingRoute` 
  * `Shopwell\Core\Content\Product\SalesChannel\Search\CachedProductSearchRoute` 
  * `Shopwell\Core\Content\Product\SalesChannel\Suggest\CachedProductSuggestRoute` 
  * `Shopwell\Core\Content\Sitemap\SalesChannel\CachedSitemapRoute` 
  * `Shopwell\Core\System\Country\SalesChannel\CachedCountryRoute` 
  * `Shopwell\Core\System\Country\SalesChannel\CachedCountryStateRoute` 
  * `Shopwell\Core\System\Currency\SalesChannel\CachedCurrencyRoute` 
  * `Shopwell\Core\System\Language\SalesChannel\CachedLanguageRoute` 
  * `Shopwell\Core\System\Salutation\SalesChannel\CachedSalutationRoute` 
___
# Upgrade Information
## Disabling caching of store-api-routes
The Cache for Store-API-Routes can now be disabled by implementing the `Shopwell\Core\Framework\Adapter\Cache\StoreApiRouteCacheKeyEvent` and calling `disableCache()` method on the event.
