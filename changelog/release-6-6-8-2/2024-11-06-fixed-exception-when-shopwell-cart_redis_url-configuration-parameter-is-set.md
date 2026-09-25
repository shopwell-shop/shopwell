---
title: Fixed exception when shopwell.cart_redis_url configuration parameter is set
issue: NEXT-39439

---
# Core
* Changed `Shopwell\Core\Checkout\DependencyInjection\CompilerPass\CartRedisCompilerPass` to fix an exception that was thrown when the `shopwell.cart_redis_url` configuration parameter was set.

