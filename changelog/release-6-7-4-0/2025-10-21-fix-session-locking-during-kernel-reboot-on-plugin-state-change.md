---
title: Fix session locking during kernel reboot on plugin state change
issue: https://github.com/shopwell-shop/shopwell/issues/12823
---
# Core
* Changed `\Shopwell\Core\Framework\Plugin\PluginLifecycleService::rebuildContainerWithNewPluginState` to save session, so session lock is released before kernel reboot.
