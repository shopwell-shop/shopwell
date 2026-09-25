---
title: Endless http cache locks
issue: NEXT-23053
author: Oliver Skroblin
author_email: o.skroblin@shopwell.com
author_github: OliverSkroblin
---
# Storefront
* Changed `\Shopwell\Storefront\Framework\Cache\CacheStore::lock`, to always set an expires date for the lock cache item.