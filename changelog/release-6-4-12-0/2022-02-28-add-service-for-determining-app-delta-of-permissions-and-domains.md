---
title: Add service for determining delta of app permissions and domains
issue: NEXT-18876
author: Frederik Schmitt
author_email: f.schmitt@shopwell.com
author_github: fschmtt
---
# Core
* Added abstract class `Shopwell\Core\Framework\App\Delta\AbstractAppConfirmationDeltaProvider`
  * Added service `Shopwell\Core\Framework\App\Delta\AppConfirmationDeltaProvider`
  * Added service `Shopwell\Core\Framework\App\Delta\PermissionsDeltaService`
* Added exception `Shopwell\Core\Framework\Store\Exception\ExtensionUpdateRequiresConsentAffirmationException`
* Deprecated exception `Shopwell\Core\Framework\Store\Exception\ExtensionRequiresNewPrivilegesException`, will be replaced with `Shopwell\Core\Framework\Store\Exception\ExtensionUpdateRequiresConsentAffirmationException`
  * Deprecated `Shopwell\Core\Framework\Store\Exception\ExtensionUpdateRequiresConsentAffirmationException` to only extend from `Shopwell\Core\Framework\ShopwellHttpException`
Changed `Shopwell\Core\Framework\App\Lifecycle\Update\AppUpdater` to catch new `Shopwell\Core\Framework\Store\Exception\ExtensionUpdateRequiresConsentAffirmationException`
* Deprecated `Shopwell\Core\Framework\Store\Services\StoreAppLifecycleService`, will be marked as internal
  * Changed `Shopwell\Core\Framework\Store\Services\StoreAppLifecycleService` to use new `Shopwell\Core\Framework\App\Delta\AppConfirmationDeltaProvider`
  * Deprecated method `Shopwell\Core\Framework\Store\Services\StoreAppLifecycleService::getAppIdByName()`
___
# Next Major Version Changes
## Deprecations in `Shopwell\Core\Framework\Store\Services\StoreAppLifecycleService`
The class `StoreAppLifecycleService` has been marked as internal.

We also removed the `StoreAppLifecycleService::getAppIdByName()` method.

## Removal of `Shopwell\Core\Framework\Store\Exception\ExtensionRequiresNewPrivilegesException`
We removed the `ExtensionRequiresNewPrivilegesException` exception.
Will be replaced with the internal `ExtensionUpdateRequiresConsentAffirmationException` exception to have a more generic one.
