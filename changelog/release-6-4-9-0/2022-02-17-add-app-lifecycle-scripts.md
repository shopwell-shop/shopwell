---
title: Add app lifecycle scripts
issue: NEXT-19855
---
# Core
* Added AppLifecycleHooks in `\Shopwell\Core\Framework\App\Event\Hooks`.
* Changed `\Shopwell\Core\Framework\App\Lifecycle\AppLifecycle` and `\Shopwell\Core\Framework\App\AppStateService` to execute the new app lifecycle hooks.
* Added `\Shopwell\Core\Framework\Script\Execution\Awareness\AppSpecificHook` to mark hooks that should be only executed for specific apps.
* Changed `\Shopwell\Core\Framework\Script\Execution\ScriptExecutor` to only execute scripts of a specific app for `AppSpecificHooks`. 
