---
title:              Check app compatibility on auto update
issue:              NEXT-13261
author:             Ramona Schwering
author_email:       r.schwering@shopwell.com
author_github:      @leichteckig
---
# Administration
* Added `openMyExtensions` method to `sw-shopwell-updates-plugins.html.twig`
___
# Core
* Added `ExtensionLifecycleService` as argument to `Shopwell\Core\Framework\Update\Api\UpdateController`
* Added `AbstractExtensionDataProvider` as argument to `Shopwell\Core\Framework\Update\Services\ApiClient`
* Added `searchCriteria` as additional parameter to `getInstalledExtensions` in `\Shopwell\Core\Framework\Store\Services\AbstractExtensionDataProvider`
* Added `getExtensionCompatibilities` method in `Shopwell\Core\Framework\Store\Services\StoreClient` 
* Added `getExtensionCompatibilities` method in `Shopwell\Core\Framework\Update\Services\PluginCompatibility` 
* Added `getExtensionsToDeactivate` method in `Shopwell\Core\Framework\Update\Services\PluginCompatibility`
* Added `getExtensionsToReactivate` method in `Shopwell\Core\Framework\Update\Services\PluginCompatibility`
* Added `fetchActiveExtensions` method in `Shopwell\Core\Framework\Update\Services\PluginCompatibility`
* Added `fetchInactiveExtensions` method in `Shopwell\Core\Framework\Update\Services\PluginCompatibility`
* Added `DeactivateExtensionsStep` class
* Removed `ReactivatePluginsStep` class due to it not being used
