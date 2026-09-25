---
title: Immediately invalidate critical caches
issue: NEXT-40112
---
# Core
* Changed `\Shopwell\Core\Framework\Adapter\Cache\CacheInvalidationSubscriber` to force the immediate invalidation of the following caches:
  * `\Shopwell\Core\System\StateMachine\Loader\InitialStateIdLoader`
  * `\Shopwell\Core\System\SystemConfig\CachedSystemConfigLoader`
  * `\Shopwell\Core\Checkout\Cart\CachedRuleLoader`
  * `\Shopwell\Core\System\SalesChannel\Context\CachedSalesChannelContextFactory`
  * `\Shopwell\Core\System\SalesChannel\Context\CachedBaseSalesChannelContextFactory`
