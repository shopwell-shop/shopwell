---
title: Improve composer executions during plugin lifecycle
issue: NEXT-36780
---

# Core

* Changed `\Shopwell\Core\Framework\Plugin\Composer\CommandExecutor` to update only directly affected packages instead of all packages.
* Changed `\Shopwell\Core\Framework\Plugin\PluginLifecycleService` to not modify vendor directory if Shopwell is in cluster mode.
