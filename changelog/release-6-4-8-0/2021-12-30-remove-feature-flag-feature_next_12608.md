---
title: Remove feature flag FEATURE_NEXT_12608
issue: NEXT-17643
---
# Core
* Removed feature flag FEATURE_NEXT_12608
* Changed 7th parameter `$pluginLifecycleService` to `$extensionLifecycleService` in `Shopwell\Core\Framework\Update\Api\UpdateController::__construct`. It will only accept object of type `Shopwell\Core\Framework\Store\Services\AbstractExtensionLifecycle`.
* Removed 10th parameter `$extensionLifecycleService` in `Shopwell\Core\Framework\Update\Api\UpdateController::__construct`.
* Changed `Shopwell\Core\Framework\App\ScheduledTask\UpdateAppsHandler::__construct`. Parameter `$appUpdater` will only accept object of type `Shopwell\Core\Framework\App\Lifecycle\Update\AbstractAppUpdater`.
* Changed `Shopwell\Core\Framework\Store\Api\StoreController::__construct`. Parameter `$extensionDataProvider` will only accept object of type `Shopwell\Core\Framework\Store\Services\AbstractExtensionDataProvider`.
* Changed signature of `Shopwell\Core\Framework\Store\Api\StoreController::getUpdateList`. Parameter `$request` was removed.
* Changed `Shopwell\Core\Framework\Store\Services\StoreClient::__construct`.
  * Parameter `$optionsProvider` will only accept object of type `Shopwell\Core\Framework\Store\Authentication\AbstractStoreRequestOptionsProvider`.
  * Parameter `$extensionLoader` will only accept object of type `Shopwell\Core\Framework\Store\Services\ExtensionLoader`.
  * Parameter `$instanceService` will only accept object of type `Shopwell\Core\Framework\Store\Services\InstanceService`.
* Removed `Shopwell\Core\Framework\Store\Services\StoreClient::getUpdatesList`.
* Changed `Shopwell\Core\Framework\Update\Services\PluginCompatibility::__construct`. Parameter `$extensionDataProvider` will only accept object of type `Shopwell\Core\Framework\Store\Services\AbstractExtensionDataProvider`.
* Removed class `Shopwell\Core\Framework\Update\Steps\DeactivatePluginsStep`.
* Removed test `Shopwell\Core\Framework\Test\Update\Steps\DeactivatePluginsStep`.
* Removed `Shopwell\Core\Framework\Store\Services\StoreService::getDefaultQueryParameters`.
* Removed `Shopwell\Core\Framework\Store\Services\StoreService::getDefaultQueryParametersFromContext`.
* Removed `Shopwell\Core\Framework\Store\Services\StoreService::getShopwellVersion`.
___
# Administration
* Removed feature flag FEATURE_NEXT_12608
* Removed feature flag condition from `sw-meteor-card`. The component is always registered now.
* Removed feature flag condition from `sw-meteor-navigation`. The component is always registered now.
* Removed feature flag condition from `sw-meteor-page`. The component is always registered now.
* Removed feature flag condition from `sw-license-violation::fetchPLugins`
* Deprecated computed property `pluginRepository` in `sw-license-violation`. It will be removed.
* Removed feature flag condition in `sw-extension/index`.
* Removed async import statements in `sw-extension/index`. Module components will now be imported synchronously.
* Removed registration of `sw-extension-error.mixin` from `sw-extension/index`.
* Removed default export in `sw-extension-error.mixin`. The registration of the mixin is a side effect of its import now.
* Deprecated computed property `cardTitle` in `sw-settings-shopwell-updates-plugins`. It will be removed.
* Deprecated method `openPluginManager` in `sw-settings-shopwell-updates-plugins`. It will be removed.
* Removed feature flag condition in `sw-shopwell-updates-plugins.html.twig`.
