---
title: Remove core dependencies from CountryStateController
issue: NEXT-21967
author: Stefan Sluiter
author_email: s.sluiter@shopwell.com
author_github: ssltg
---
# Core
* Changed `Shopwell\Core\Framework\Adapter\Cache\CacheInvalidationSubscriber` by adding new invalidation for country_states
* Added `Shopwell\Core\System\Country\Event\CountryStateRouteCacheKeyEvent`
* Added `Shopwell\Core\System\Country\Event\CountryStateRouteCacheTagsEvent`
* Added `Shopwell\Core\System\Country\SalesChannel\AbstractCountryStateRoute`
* Added `Shopwell\Core\System\Country\SalesChannel\CountryStateRoute`
* Added new Store-Api Route `/country-state` to get all states of a given countryId
* Added `Shopwell\Core\System\Country\SalesChannel\CachedCountryStateRoute`
* Added `Shopwell\Core\System\Country\SalesChannel\CountryStateRouteResponse`
___
# Storefront
* Added `Shopwell\Storefront\Pagelet\Country\CountryStateDataPagelet`
* Added `Shopwell\Storefront\Pagelet\Country\CountryStateDataPageletCriteriaEvent`
* Added `Shopwell\Storefront\Pagelet\Country\CountryStateDataPageletLoadedHook` with hook name `country-sate-data-pagelet-loaded`
* Added `Shopwell\Storefront\Pagelet\Country\CountryStateDataPageletLoadedEvent`
* Added `Shopwell\Storefront\Pagelet\Country\CountryStateDataPageletLoader`
* Changed `Shopwell\Storefront\Controller\CountryStateController` to use `Shopwell\Storefront\Pagelet\Country\CountryStateDataPageletLoader`
* Changed method `requestStateData` to use `stateRequired` as third parameter in `/Storefront/Resources/app/storefront/src/plugin/forms/form-country-state-select.plugin.js`
* Changed options of `AddressCountry`-select by adding a new data value `data-state-required` in `Storefront/Resources/views/storefront/component/address/address-form.html.twig`
* Deprecated `$countryRoute` in constructor of `Shopwell\Storefront\Controller\CountryStateController`
* Deprecated `stateRequired` as Response in `\Shopwell\Storefront\Controller\CountryStateController::getCountryData`
___
# Upgrade Information
## Update `requestStateData` method in `form-country-state-select.plugin.js`
The method `requestStateData` will require the third parameter `stateRequired` to be set from the calling instance.
It will no longer be provided by the endpoint of `frontend.country.country-data`.
The value can be taken from the selected country option in `data-state-required` 