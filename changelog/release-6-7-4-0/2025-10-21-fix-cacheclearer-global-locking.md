---
title: Fix CacheClearer global locking
issue: https://github.com/shopwell-shop/shopwell/pull/13079
author: Andrii Havryliuk
author_email: a.havryliuk@shopwell.com
author_github: @Andrii Havryliuk
---
# Core
* Changed `\Shopwell\Core\Framework\Adapter\Cache\CacheClearer::clearContainerCache` to use current cache directory in the lock key, decreased lock timeout to 5 seconds, improved exception processing.
