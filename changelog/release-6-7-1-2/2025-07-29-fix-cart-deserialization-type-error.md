---
title: Fix cart deserialization type error
issue: 11155
author: Max Stegmeyer
---
# Core
* Changed `\Shopwell\Core\Checkout\Cart\CartPersister` and `Shopwell\Core\Checkout\Cart\RedisCartPersister` to catch all Errors, not just Exceptions, during cart deserialization, to disregard the existing, incompatible cart instead of failing.
