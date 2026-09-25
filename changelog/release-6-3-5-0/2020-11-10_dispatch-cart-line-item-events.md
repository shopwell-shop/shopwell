---
title: Dispatch cart line item events
issue: NEXT-12478
author: Kevin C.
author_email: kevin.chen@perfecthair.ch
author_github: @maqavelli
---
# Core
* Added new events `Shopwell\Core\Checkout\Cart\Event\AfterLineItemAddedEvent`, `Shopwell\Core\Checkout\Cart\Event\AfterLineItemRemovedEvent`, `Shopwell\Core\Checkout\Cart\Event\AfterLineItemQuantityChangedEvent`.
* Changed method `add()` in `Shopwell\Core\Checkout\Cart\SalesChannel\CartItemAddRoute` to dispatch `Shopwell\Core\Checkout\Cart\Event\AfterLineItemAddedEvent` when an item is added to the cart.
* Changed method `remove()` in `Shopwell\Core\Checkout\Cart\SalesChannel\CartItemRemoveRoute` to dispatch `Shopwell\Core\Checkout\Cart\Event\AfterLineItemRemovedEvent` when an item is removed from the cart.
* Changed method `change()` in `Shopwell\Core\Checkout\Cart\SalesChannel\CartItemUpdateRoute` to dispatch `Shopwell\Core\Checkout\Cart\Event\AfterLineItemQuantityChangedEvent` when the quantity of an item in the cart is changed.
* Deprecated `Shopwell\Core\Checkout\Cart\Event\LineItemAddedEvent` and is replaced with `Shopwell\Core\Checkout\Cart\Event\BeforeLineItemAddedEvent`.
* Deprecated `Shopwell\Core\Checkout\Cart\Event\LineItemRemovedEvent` and is replaced with `Shopwell\Core\Checkout\Cart\Event\BeforeLineItemRemovedEvent`.
* Deprecated `Shopwell\Core\Checkout\Cart\Event\LineItemQuantityChangedEvent` and is replaced with `Shopwell\Core\Checkout\Cart\Event\BeforeLineItemQuantityChangedEvent`.
