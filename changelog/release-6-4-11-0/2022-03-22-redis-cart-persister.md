---
title: Redis cart persister
issue: NEXT-20672
author: Oliver Skroblin
author_email: o.skroblin@shopwell.com
author_github: OliverSkroblin
---
# Core
* Added `\Shopwell\Core\Checkout\Cart\RedisCartPersister`, which allows to persist the carts in Redis.
* Added `shopwell.cart.redis_url` config option to configure the Redis URL for the cart persister.
* Added `shopwell.cart.compress` config option to configure the compression of the cart data. This is not taken into account in the sql persister
* Deprecated `\Shopwell\Core\Checkout\Cart\CartPersisterInterface`, use `\Shopwell\Core\Checkout\Cart\AbstractCartPersister` instead
* Added new required parameter, with v6.5.0.0, `salesChannelId` in `\Shopwell\Core\System\SalesChannel\Context\SalesChannelContextPersister::delete`