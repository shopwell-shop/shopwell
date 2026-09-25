---
title: Fetch logininformation from store
issue: NEXT-20617
author: Sebastian Franze
author_email: s.franze@shopwell.com
author_github: Sebastian Franze
---
# Core
* Added migration `Migration1650981517RemoveShopwellId`.
* Removed system config key `core.store.shopwellId`.
* Changed `Shopwell\Core\Framework\Store\Services\StoreClient::loginWithShopwellId`. Method will not write system config entry `core.store.shopwellId` anymore.
* Changed `Shopwell\Core\Framework\Store\Services\FirstRunWizardClient::frwLogin`. Method will not write system config entry `core.store.shopwellId` anymore.
* Added new parameter `user_info` to `shopwell.store_endpoints`.
___
# API
* Changed Response of `/api/_action/store/checklogin`. Response now contains key `userInfo` with information about the sw acount.
___
# Administration
* Added new global types `ShopwellHttpError` and `StoreApiException`.
* Added new type `UserInfo` in `src/core/service/api/store.api.service.ts`.
* Changed `src/module/sw-extension/page/sw-extension-my-extensions-account/index.js` to `src/module/sw-extension/page/sw-extension-my-extensions-account/index.ts`.
* Deprecated method `loginShopwellUser` in `src/module/sw-extension/page/sw-extension-my-extensions-account/index.ts`. Use Method `login` instead
* Changed `src/module/sw-extension/service/extension-error-handler.service.js` to `src/module/sw-extension/service/extension-error-handler.service.ts`.
* Added new type `MappedError` in `src/module/sw-extension/service/extension-error-handler.service.ts`.
* Added new field `userInfo` in `ShopwellExtensionsState`.
* Added mutation `setUserInfo` in `ShopwellExtensionsState`.
* Deprecated field `shopwellId` in `ShopwellExtensionsState`. Check existence of `userInfo` instead.
* Deprecated field `loginStatus` in `ShopwellExtensionsState` Check existence of `userInfo` instead.
* Deprecated mutation `storeShopwellId` in `ShopwellExtensionsState`. Mutation will be removed without replacement.
* Deprecated mutation `setLoginStatus` in `ShopwellExtensionsState`. Mutation will be removed without replacement.
