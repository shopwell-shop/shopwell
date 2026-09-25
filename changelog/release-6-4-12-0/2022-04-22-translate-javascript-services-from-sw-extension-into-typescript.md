---
title: Translate javascript services from sw-extension into TypeScript
issue: NEXT-20617
author: Sebastian Franze
author_email: s.franze@shopwell.com
---
# Administration
* Removed es-lint rule `@typescript-eslint/explicit-function-return-type`.
* Changed `src/app/service/discount-campaign.service.js` to `src/app/service/discount-campaign.service.ts`.
* Added interface `ShopwellDiscountCampaignService` in `src/app/service/discount-campaign.service.ts`.
* Changed `src/app/state/shopwell-apps.store.js` to `src/app/state/shopwell-apps.store.ts`.
* Added interface `ShopwellAppsState` in `src/app/state/shopwell-apps.store.ts`.
* Changed `src/core/service/api/app-modules.service.js` to `src/core/service/api/app-modules.service.ts`.
* Added type `AppModuleDefinition` in `src/core/service/api/app-modules.service.ts`.
* Added type `AppModulesService` in `src/core/service/api/app-modules.service.ts`.
* Changed `src/core/service/api/store.api.service.js` to `src/core/service/api/store.api.service.ts`.
* Added type `StoreApiService` in `src/core/service/api/store.api.service.ts`.
* Deprecated `StoreApiService::getBasicParams`. Method will be private in future versions.
* Added type parameter to`ApiService::handleResponse`.
* Changed return type of `ApiService::handleResponse` to `AxiosResponse<T>|T|unknown`.
* Changed `src/module/sw-extension/service/extension-store-action.service.js` to `src/module/sw-extension/service/extension-store-action.service.ts`.
* Added type `ExtensionStoreActionService` in `src/module/sw-extension/service/extension-store-action.service.js`.
* Added type `ExtensionVariantType` in `src/module/sw-extension/service/extension-store-action.service.js`.
* Added type `ExtensionType` in `src/module/sw-extension/service/extension-store-action.service.js`.
* Added type `DiscountCampaign` in `src/module/sw-extension/service/extension-store-action.service.js`.
* Added type `ExtensionVariant` in `src/module/sw-extension/service/extension-store-action.service.js`.
* Added type `StoreCategory` in `src/module/sw-extension/service/extension-store-action.service.js`.
* Added type `License` in `src/module/sw-extension/service/extension-store-action.service.js`.
* Added type `Extension` in `src/module/sw-extension/service/extension-store-action.service.js`.
* Deprecated `ExtensionStoreActionService::basicHeaders`. Use private method `storeHeaders` instead.
* Changed `src/module/sw-extension/service/index.js` to `src/module/sw-extension/service/index.ts`.
* Added entry `extensionStoreActionService: ExtensionStoreActionService` to global interface `ServiceContainer` in `src/module/sw-extension/service/index.js`.
* Added entry `shopwellExtensionService: ShopwellExtensionService` to global interface `ServiceContainer` in `src/module/sw-extension/service/index.js`.
* Added entry `extensionErrorService: ExtensionErrorService` to global interface `ServiceContainer` in `src/module/sw-extension/service/index.js`.
* Changed `src/module/sw-extension/service/shopwell-extension.service.js` to `src/module/sw-extension/service/shopwell-extension.service.ts`.
* Added type `ShopwellExtensionService` in `src/module/sw-extension/service/shopwell-extension.service.ts`.
* Added optional parameter `storeApiService` to `ShopwellExtensionService`.
* Deprecated constructor parameter `storeApiService` in `ShopwellExtensionService`. Parameter will be required in future versions.
* Deprecated `ShopwellExtensionService::updateModules`. Method will be private in future versions.
* Deprecated `ShopwellExtensionService::_getLinkToTheme`. Will be removed. Use private function `getLinkToTheme` instead.
* Deprecated `ShopwellExtensionService::_getLinkToApp`. Will be removed. Use private function `getLinkToApp` instead.
* Deprecated `ShopwellExtensionService::_getAppFromStore`. Will be removed. Use private function `getAppFromStore` instead.
* Deprecated `ShopwellExtensionService::_appHasMainModule`. Will be removed. Use private function `appHasMainModule` instead.
* Deprecated `ShopwellExtensionService::_createLinkToModule`. Will be removed. Use private function `createLinkToModule` instead.
* Deprecated `ShopwellExtensionService::_orderByType`. Will be removed. Use private function `orderByType` instead.
* Changed `src/module/sw-extension/store/extensions.store.js` to `src/module/sw-extension/store/extensions.store.ts`
* Added type `ShopwellExtensionsState` in `src/module/sw-extension/store/extensions.store.js`
* Deprecated entries `licensedExtensions`, `plugins` and `totalPlugins` in `ShopwellExtensionsState`
* Deprecated entry `plugins` in `ShopwellExtensionsState`
* Deprecated entry `totalPlugins` in `ShopwellExtensionsState`
* Deprecated mutation `loadLicensedExtensions` in `shopwellExtensionsStore`. Will be removed with no replacement.
* Deprecated mutation `licensedExtensions` in `shopwellExtensionsStore`. Will be removed with no replacement.
* Deprecated mutation `commitPlugins` in `shopwellExtensionsStore`. Will be removed with no replacement.
* Changed `src/module/sw-extension/store/index.js` to `src/module/sw-extension/store/index.ts`
* Changed type of entry `acl` in global interface `ServiceContainer` to `AclService`
* Changed type of entry `shopwellDiscountCampaignService` in global interface `ServiceContainer` to `ShopwellDiscountCampaignService`
* Changed type of entry `storeService` in global interface `ServiceContainer` to `StoreApiService`
* Added entry `appModulesService: AppModulesService` to global interface `ServiceContainer`
* Added the following entries to the global interface `VuexRootState`:
  * `session.userPending: boolean`
  * `session.languageId: string`
  * `session.currentLocale: string|null`
  * `shopwellExtensions: ShopwellExtensionsState`
  * `extensionEntryRoutes: $TSFixMe`
  * `shopwellApps: ShopwellAppsState`
