---
title: Introduce a new interface for events containing the SalesChannelContext 
issue: NEXT-11926
author: Michael Telgmann
---
# Core
* Added new interface `Shopwell\Core\Framework\Event\ShopwellSalesChannelEvent`. Use it to indicate that your event contains the `Shopwell\Core\System\SalesChannel\SalesChannelContext`
* Added `Shopwell\Core\Framework\Event\ShopwellSalesChannelEvent` interface to the following event classes
  * `Shopwell\Core\Checkout\Cart\Order\CartConvertedEvent`
  * `Shopwell\Core\Checkout\Customer\Event\CustomerAccountRecoverRequestEvent`
  * `Shopwell\Core\Checkout\Customer\Event\CustomerBeforeLoginEvent`
  * `Shopwell\Core\Checkout\Customer\Event\CustomerChangedPaymentMethodEvent`
  * `Shopwell\Core\Checkout\Customer\Event\CustomerDeletedEvent`
  * `Shopwell\Core\Checkout\Customer\Event\CustomerLoginEvent`
  * `Shopwell\Core\Checkout\Customer\Event\CustomerLogoutEvent`
  * `Shopwell\Core\Checkout\Customer\Event\CustomerRegisterEvent`
  * `Shopwell\Core\Checkout\Customer\Event\CustomerWishlistLoaderCriteriaEvent`
  * `Shopwell\Core\Checkout\Customer\Event\CustomerWishlistProductListingResultEvent`
  * `Shopwell\Core\Content\Category\Event\NavigationLoadedEvent`
  * `Shopwell\Core\Content\Cms\Events\CmsPageLoadedEvent`
  * `Shopwell\Core\Content\Cms\Events\CmsPageLoaderCriteriaEvent`
  * `Shopwell\Core\Content\Product\Events\ProductCrossSellingCriteriaEvent`
  * `Shopwell\Core\Content\Product\Events\ProductCrossSellingsLoadedEvent`
  * `Shopwell\Core\Content\Product\Events\ProductListingCollectFilterEvent`
  * `Shopwell\Core\Content\Product\Events\ProductListingCriteriaEvent`
  * `Shopwell\Core\Content\Product\Events\ProductListingResultEvent`
  * `Shopwell\Core\Framework\Routing\Event\SalesChannelContextResolvedEvent`
  * `Shopwell\Core\System\SalesChannel\Entity\SalesChannelEntityAggregationResultLoadedEvent`
  * `Shopwell\Core\System\SalesChannel\Entity\SalesChannelEntityIdSearchResultLoadedEvent`
  * `Shopwell\Core\System\SalesChannel\Entity\SalesChannelEntityLoadedEvent`
  * `Shopwell\Core\System\SalesChannel\Entity\SalesChannelEntitySearchResultLoadedEvent`
  * `Shopwell\Core\System\SalesChannel\Event\SalesChannelContextPermissionsChangedEvent`
  * `Shopwell\Core\System\SalesChannel\Event\SalesChannelContextSwitchEvent`
  * `Shopwell\Core\System\SalesChannel\Event\SalesChannelContextTokenChangeEvent`
* Deprecated following classes which will implement the `Shopwell\Core\Framework\Event\ShopwellSalesChannelEvent` interface with Shopwell 6.4.0.0
  * `Shopwell\Core\Checkout\Cart\Event\CartDeletedEvent`
  * `Shopwell\Core\Checkout\Cart\Event\CartMergedEvent`
  * `Shopwell\Core\Checkout\Cart\Event\CartSavedEvent`
  * `Shopwell\Core\Checkout\Cart\Event\LineItemAddedEvent`
  * `Shopwell\Core\Checkout\Cart\Event\LineItemQuantityChangedEvent`
  * `Shopwell\Core\Checkout\Cart\Event\LineItemRemovedEvent`
* Deprecated following methods which will return `Shopwell\Core\Framework\Context` with Shopwell 6.4.0.0. Use `getSalesChannelContext()` to get the `Shopwell\Core\System\SalesChannel\SalesChannelContext` instead.
  * `Shopwell\Core\Checkout\Cart\Event\CartDeletedEvent::getContext()`
  * `Shopwell\Core\Checkout\Cart\Event\CartMergedEvent::getContext()`
  * `Shopwell\Core\Checkout\Cart\Event\CartSavedEvent::getContext()`
  * `Shopwell\Core\Checkout\Cart\Event\LineItemAddedEvent::getContext()`
  * `Shopwell\Core\Checkout\Cart\Event\LineItemQuantityChangedEvent::getContext()`
  * `Shopwell\Core\Checkout\Cart\Event\LineItemRemovedEvent::getContext()`
___
# Storefront
* Added `Shopwell\Core\Framework\Event\ShopwellSalesChannelEvent` interface to the following event classes
  * `Shopwell\Storefront\Event\StorefrontRenderEvent`
  * `Shopwell\Storefront\Event\RouteRequest\RouteRequestEvent`
  * `Shopwell\Storefront\Page\PageLoadedEvent`
  * `Shopwell\Storefront\Page\Address\Listing\AddressListingCriteriaEvent`
  * `Shopwell\Storefront\Page\Product\ProductLoaderCriteriaEvent`
  * `Shopwell\Storefront\Page\Product\CrossSelling\CrossSellingLoadedEvent`
  * `Shopwell\Storefront\Page\Product\CrossSelling\CrossSellingProductCriteriaEvent`
  * `Shopwell\Storefront\Page\Product\Review\ProductReviewsLoadedEvent`
  * `Shopwell\Storefront\Pagelet\PageletLoadedEvent`
* Deprecated following classes which will implement the `Shopwell\Core\Framework\Event\ShopwellSalesChannelEvent` interface with Shopwell 6.4.0.0
  * `Shopwell\Storefront\Page\Product\QuickView\MinimalQuickViewPageCriteriaEvent`
  * `Shopwell\Storefront\Page\Product\ProductPageCriteriaEvent`
* Deprecated following methods which will return the `Shopwell\Core\Framework\Context` with Shopwell 6.4.0.0. Use `getSalesChannelContext()` to get the `Shopwell\Core\System\SalesChannel\SalesChannelContext` instead.
  * `Shopwell\Storefront\Page\Product\QuickView\MinimalQuickViewPageCriteriaEvent::getContext()`
  * `Shopwell\Storefront\Page\Product\ProductPageCriteriaEvent::getContext()`
