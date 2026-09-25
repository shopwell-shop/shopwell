---
title: Add lock for CacheClearer
issue: 10706
author: nfortier
author_email: n.fortier@shopwell.com
author_github: nfortier-shopwell
---

# Core
* Added a lock of 30s and shared key to `\Shopwell\Core\Framework\Adapter\CacheCacheClearer` for `cleanupOldContainerCacheDirectories` and `clearContainerCache` methods to limit concurrent execution resulting in fatal error due to PHP files destroyed by one process and required by the other.
