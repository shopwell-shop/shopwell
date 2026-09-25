---
title: Cache invalidation manipulation
issue: NEXT-28562
author: Oliver Skroblin
author_email: o.skroblin@shopwell.com
author_github: OliverSkroblin
---
# Core
* Changed `\Shopwell\Core\Framework\Adapter\Cache\CacheInvalidationSubscriber` to allow manipulation of the cache invalidation event via compiler passes. The class isn't @internal anymore and can be manipulated via compiler pass by removing the event listener tags.
* Added `\Shopwell\Core\Framework\DependencyInjection\CompilerPass\RemoveEventListener` class, for easier removal of event listeners via compiler pass.
