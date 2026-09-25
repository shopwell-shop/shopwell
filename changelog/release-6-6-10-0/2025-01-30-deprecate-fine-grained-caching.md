---
title: Deprecate fine-grained caching
issue: NEXT-40494
---
# Core
* Changed `\Shopwell\Core\Framework\Adapter\Cache\CacheInvalidationSubscriber` and `\Shopwell\Core\System\SystemConfig\SystemConfigService` to tag system configs per sales channel.
* Added `$salesChannelId` parameter to `\Shopwell\Core\System\SystemConfig\Event\SystemConfigChangedHook`.
* Deprecated `\Shopwell\Core\Framework\Adapter\Cache\CacheInvalidationSubscriber` as it will become internal in the future.
* Deprecated `\Shopwell\Core\System\SystemConfig\Event\SystemConfigChangedHook` as it will become @final in the future.
* Deprecated all config values under `shopwell.cache.tagging`
___ 
# Upgrade Information 
## SalesChannelId is available in SystemConfigChangedHook
The SalesChannelId is now available in the SystemConfigChangedHook (`app.config.changed`). The request formats now looks like this:*
```diff
{
  "changes": [...],
+  "salesChannelId": "00000"
}
```
