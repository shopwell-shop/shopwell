---
title: Reduce event bus usage for AddCacheTagEvent
author: Benjamin Wittwer
author_email: Discord.Benjamin@web.de
author_github: gecolay
---
# Core
* Added `addTag` method to `Shopwell\Core\Framework\Adapter\Cache\CacheTagCollector`
* Changed `Shopwell\Core\Framework\Adapter\Translation\Translator` to use `addTag` method from `CacheTagCollector`
* Changed `Shopwell\Core\System\SystemConfig\SystemConfigService` to use `addTag` method from `CacheTagCollector`
___
# Storefront
* Changed `Shopwell\Storefront\Theme\ThemeConfigValueAccessor` to use `addTag` method from `CacheTagCollector`
