---
title: Add `ResetInterface` to stateful services.
issue: NEXT-20253
---
# Core
* Added `ResetInterface` to all services having internal state, that needs to be reset between requests, and tagged them with `kernel.reset` tag.
* Deprecated `\Shopwell\Core\Framework\Adapter\Twig\EntityTemplateLoader::clearInternalCache()`, use `reset()` instead.
* Deprecated the TestBehaviourTraits `\Shopwell\Core\Content\Test\ImportExport\SerializerCacheTestBehaviour`, `\Shopwell\Core\Framework\Test\App\StorefrontPluginRegistryTestBehaviour`, `\Shopwell\Core\Framework\Test\TestCaseBase\RuleTestBehaviour` and `\Shopwell\Core\Framework\Test\TestCaseBase\SystemConfigTestBehaviour` as they are not needed anymore, if you use them in your unit test remove the usage.
___
# Storefront
* Added `ResetInterface` to `\Shopwell\Storefront\Theme\StorefrontPluginRegistry` and tagged it with the `kernel.reset` tag.
___
# Next Major Version Changes
## Removal of `\Shopwell\Core\Framework\Adapter\Twig\EntityTemplateLoader::clearInternalCache()`

We removed `\Shopwell\Core\Framework\Adapter\Twig\EntityTemplateLoader::clearInternalCache()`, use `reset()` instead.
