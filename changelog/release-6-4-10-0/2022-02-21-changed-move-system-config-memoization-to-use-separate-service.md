---
title: Changed system config memoization to use separate service.
issue: https://github.com/shopwell-shop/platform/issues/2319
author: Andreas Allacher
author_email: andreas.allacher@massiveart.com
author_github: @AndreasA
---
# Core
* Changed `Shopwell\Core\System\SystemConfig\SystemConfigService` to not memoize the system configuration.
* Added `Shopwell\Core\System\SystemConfig\Store\MemoizedSystemConfigStore` to memoize the system configuration and clear it upon changes.
* Added `Shopwell\Core\System\SystemConfig\MemoizedSystemConfigLoader` to memoize the system configuration in `Shopwell\Core\System\SystemConfig\Store\MemoizedSystemConfigStore`.
