---
title: Fix DisableExtensionsCompilerPass
issue: NEXT-21579
---
# Core
* Changed `\Shopwell\Core\Framework\DependencyInjection\CompilerPass\DisableExtensionsCompilerPass` to correctly override `ActiveAppsLoader`-service if `DISABLE_EXTENSIONS` is set.
* Changed `\Shopwell\Core\Framework\Framework` to register `DisableExtensionsCompilerPass` as compiler pass.
