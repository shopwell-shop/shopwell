---
title: Rearrange CartSavedEvent in RedisCartPersister
issue: https://github.com/shopwell-shop/shopwell/issues/6872
author_github: @En0Ma1259
---
# Core
* Changed dispatch event order in `Shopwell\Core\Checkout\Cart\RedisCartPersister`. `Shopwell\Core\Checkout\Cart\Event\CartSavedEvent` will be dispatched after setting the cart. Align dispatch order with `Shopwell\Core\Checkout\Cart\CartPersister`
