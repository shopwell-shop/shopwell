---
title: Event interfaces for extension events
author: Michel Bade
author_email: m.bade@shopwell.com
author_github: @cyl3x
---
# Core
* Changed `Shopwell\Core\Checkout\Cart\Extension\CheckoutCartRuleLoaderExtension` to implement `Shopwell\Core\Framework\Event\ShopwellSalesChannelEvent` and `Shopwell\Core\Checkout\Cart\Event\CartEvent`
* Changed `Shopwell\Core\Checkout\Cart\Extension\CheckoutPlaceOrderExtension` to implement `Shopwell\Core\Framework\Event\ShopwellSalesChannelEvent` and `Shopwell\Core\Checkout\Cart\Event\CartEvent`
