---
title: Load all hookable entities dynamically by checking shopwell.entity.hookable tag
---
# Core
* Added new service tag `shopwell.entity.hookable` in `Shopwell\Core\Framework\DependencyInjection\CompilerPass\AutoconfigureCompilerPass`
* Added new interface `Shopwell\Core\Framework\Webhook\Hookable\HookableEntityInterface`
* Changed `Shopwell\Core\Framework\Webhook\Hookable\HookableEventCollector::getHookableEntities` to load hookable entities dynamically
