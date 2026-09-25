---
title: Fix execution of store-api scripts over GET requests
issue: NEXT-21122
---
# Core
* Changed `\Shopwell\Core\Framework\Script\Api\StoreApiCacheKeyHook` to directly initializing the `cacheKey` property, thus fixing a problem when app scripts did not implement the `cache_key` block.
