---
title: Fix incorrect overwrite of deepLinkCode on order recalculation
author: Max Stegmeyer
author_email: m.stegmeyer@shopwell.com
author_github: @mstegmeyer
---
# Core
* Changed `Shopwell\Core\Checkout\Cart\Order\Transformer\CartTransformer` to not overwrite the `deeplinkCode` of an order when not requested.
* Added option `includePersistentData` to `Shopwell\Core\Checkout\Cart\Order\OrderConversionContext`
* Deprecated option `includeOrderDate` in `Shopwell\Core\Checkout\Cart\Order\OrderConversionContext` to be replaced with `includePersistentData`
