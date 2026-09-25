---
title: Make store request options exchangeable
issue: NEXT-12609
---
# Core
* Added abstract class `Shopwell\Core\Framework\Store\Authentication\AbstractStoreRequestOptionsProvider`.
* Added `Shopwell\Core\Framework\Store\Authentication\StoreRequestOptionsProvider` to provide header and query parameter for store requests.
* Added `Shopwell\Core\Framework\Store\Authentication\FrwRequestOptionsProvider` to provide header and query parameters for first run wizard api.
* Added `Shopwell\Core\Framework\Store\Services\InstanceService` to provide `shopwellVersion` and `instanceId`
* Added `Shopwell\Core\Framework\Store\Authentication\LocaleProvider` to provide the locale of the current user in requests.
* Changed super class from `AbstractStoreController` to `AbstractController` for `FirstRunWizardController`.
* Changed super class from `AbstractStoreController` to `AbstractController` for `StoreController`.
* Changed behaviour of `FirstRunWizardClient::frwLogin` and `FirstRunWizardClient::upgradeAccessToken`. Both update the users store token now automatically.
* Changed behaviour of `StoreClient::loginWithShopwellId`. It updates the users store token now automatically.
* Changed return type of `Shopwell\Core\Framework\Store\Authentication\AbstractAuthenticationProvider::getUserStoreToken()` from `string` to `?string`
* Changed return type of `Shopwell\Core\Framework\Store\Authentication\AuthenticationProvider::getUserStoreToken()` from `string` to `?string`
* Removed `final` keyword of constructor for `Shopwell\Core\Framework\Store\Services\StoreClient`
* Deprecated `Shopwell\Core\Framework\Store\Services\StoreService::getDefaultQueryParameters`. Use `Shopwell\Core\Framework\Store\Services\StoreService::getDefaultQueryParametersFromContext` instead.
* Deprecated `Shopwell\Core\Framework\Store\Services\StoreService::getShopwellVersion`. Use `Shopwell\Core\Framework\Store\Services\InstanceService::getShopwellVersion` instead.
* Deprecated `Shopwell\Core\Framework\Store\Api\AbstractStoreController`. It will be removed without any replacement.
* Deprecated `Shopwell\Core\Framework\Store\Authentication\AbstractStoreRequestOptionsProvider::getDefaultQueryParameters`. In the future this function takes an `Context` object as it's only parameter.
