---
title: Remove Storefront dependency from Core
issue: NEXT-8572
---
# API
* Changed route `api.custom.updateapi.finish` to only return redirect to administration if the administration is installed, otherwise status 204 (NO_CONTENT) will be returned
* Changed route `api.action.captcha.list` to be only available if storefront is installed, otherwise it will result in a 404
___
# Administration
* Deprecated `\Shopwell\Administration\Service\AdminOrderCartService`, use `\Shopwell\Core\Checkout\Cart\ApiOrderCartService` instead
___
# Core
* Added `\Shopwell\Core\Checkout\Cart\ApiOrderCartService`
* Changed `\Shopwell\Core\Framework\Api\Controller\SalesChannelProxyController` to use `ApiOrderCartService` instead of the deprecated `AdminOrderCartService`
* Added `\Shopwell\Core\Checkout\Customer\Event\AddressListingCriteriaEvent`
* Changed `\Shopwell\Core\Checkout\Customer\SalesChannel\ListAddressRoute` to additionally dispatch the new `\Shopwell\Core\Checkout\Customer\Event\AddressListingCriteriaEvent`
* Added `\Shopwell\Core\Content\ProductExport\Event\ProductExportContentTypeEvent`
* Changed `\Shopwell\Core\Content\ProductExport\SalesChannel\ExportController` to additionally dispatch the new `\Shopwell\Core\Content\ProductExport\Event\ProductExportContentTypeEvent`
* Changed `\Shopwell\Core\DevOps\System\Command\SystemInstallCommand` to only execute tasks that are available in the installation
* Deprecated `\Shopwell\Core\Framework\Adapter\Asset\ThemeAssetPackage`, use `\Shopwell\Storefront\Theme\ThemeAssetPackage` instead
* Added `\Shopwell\Core\Framework\Adapter\Twig\Extension\SwSanitizeTwigFilter`
* Changed `\Shopwell\Core\Framework\App\AppUrlChangeResolver\UninstallAppsStrategy` to make dependency on `ThemeAppLifecycleHandler` optional
* Changed `\Shopwell\Core\Framework\Store\Helper\PermissionCategorization` to use private constants, instead of depending on the storefront
* Changed `\Shopwell\Core\Framework\Store\Services\ExtensionLoader` to make dependency on `theme.repository` optional
* Changed `\Shopwell\Core\Framework\Store\Services\StoreAppLifecycleService` to make dependency on `theme.repository` optional
* Changed `\Shopwell\Core\Framework\Update\Api\UpdateController` to only redirect to the administration, if the administration is installed on finish request
* Changed `\Shopwell\Core\System\User\Recovery\UserRecoveryService` to fallback on `APP_URL` when generating the administration url
* Changed `\Shopwell\Core\HttpKernel` to only use HttpCache if it is available (storefront is installed)
* Removed `\Shopwell\Core\Framework\Api\Controller\CaptchaController`
___
# Storefront
* Deprecated `\Shopwell\Storefront\Page\Address\Listing\AddressListingCriteriaEvent`, use `\Shopwell\Core\Checkout\Customer\Event\AddressListingCriteriaEvent` instead
* Deprecated `\Shopwell\Storefront\Event\ProductExportContentTypeEvent`, use `\Shopwell\Core\Content\ProductExport\Event\ProductExportContentTypeEvent` instead
* Added `\Shopwell\Storefront\Theme\ThemeAssetPackage`
* Removed `\Shopwell\Storefront\Framework\Twig\Extension\SwSanitizeTwigFilter`
* Added `\Shopwell\Storefront\Controller\Api\CaptchaController`
* Changed `\Shopwell\Storefront\Framework\Cache\CacheResponseSubscriber` to add HttpCache-Annotation to cached core routes
___
# Upgrade Information
## Deprecation of AdminOrderCartService

The `\Shopwell\Administration\Service\AdminOrderCartService` was deprecated and will be removed in v6.5.0.0, please use the newly added `\Shopwell\Core\Checkout\Cart\ApiOrderCartService` instead. 

## Deprecation of Shopwell\Storefront\Page\Address\Listing\AddressListingCriteriaEvent

The `\Shopwell\Storefront\Page\Address\Listing\AddressListingCriteriaEvent` was deprecated and will be removed in v6.5.0.0, if you subscribed to the event please use the newly added `\Shopwell\Core\Checkout\Customer\Event\AddressListingCriteriaEvent` instead.

## Deprecation of Shopwell\Storefront\Event\ProductExportContentTypeEvent

The `\Shopwell\Storefront\Event\ProductExportContentTypeEvent` was deprecated and will be removed in v6.5.0.0, if you subscribed to the event please use the newly added `\Shopwell\Core\Content\ProductExport\Event\ProductExportContentTypeEvent` instead.

## Deprecation of Shopwell\Core\Framework\Adapter\Asset\ThemeAssetPackage

The `\Shopwell\Core\Framework\Adapter\Asset\ThemeAssetPackage` was deprecated and will be removed in v6.5.0.0, please use the newly added `\Shopwell\Storefront\Theme\ThemeAssetPackage` instead. 
