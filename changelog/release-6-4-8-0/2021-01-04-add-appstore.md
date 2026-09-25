---
title: Add Appstore
issue: NEXT-12608
---

# Core

* Added following new classes:
    * `Shopwell\Core\Framework\Store\Api\ExtensionStoreActionsController`
    * `Shopwell\Core\Framework\Store\Api\ExtensionStoreCategoryController`
    * `Shopwell\Core\Framework\Store\Api\ExtensionStoreDataController`
    * `Shopwell\Core\Framework\Store\Api\ExtensionStoreLicensesController`
    * `Shopwell\Core\Framework\Store\Authentication\AbstractAuthenticationProvider`
    * `Shopwell\Core\Framework\Store\Authentication\AuthenticationProvider`
    * `Shopwell\Core\Framework\Store\Exception\ExtensionInstallException`
    * `Shopwell\Core\Framework\Store\Exception\ExtensionNotFoundException`
    * `Shopwell\Core\Framework\Store\Exception\ExtensionThemeStillInUseException`
    * `Shopwell\Core\Framework\Store\Exception\InvalidExtensionIdException`
    * `Shopwell\Core\Framework\Store\Exception\InvalidExtensionRatingValueException`
    * `Shopwell\Core\Framework\Store\Exception\InvalidVariantIdException`
    * `Shopwell\Core\Framework\Store\Exception\LicenseNotFoundException`
    * `Shopwell\Core\Framework\Store\Exception\VariantTypesNotAllowedException`
    * `Shopwell\Core\Framework\Store\Helper\PermissionCategorization`
    * `Shopwell\Core\Framework\Store\Search\EqualsFilterStruct`
    * `Shopwell\Core\Framework\Store\Search\ExtensionCriteria`
    * `Shopwell\Core\Framework\Store\Search\FilterStruct`
    * `Shopwell\Core\Framework\Store\Search\MultiFilterStruct`
    * `Shopwell\Core\Framework\Store\Services\AbstractExtensionDataProvider`
    * `Shopwell\Core\Framework\Store\Services\AbstractExtensionStoreLicensesService`
    * `Shopwell\Core\Framework\Store\Services\AbstractStoreAppLifecycleService`
    * `Shopwell\Core\Framework\Store\Services\AbstractStoreCategoryProvider`
    * `Shopwell\Core\Framework\Store\Services\ExtensionDataProvider`
    * `Shopwell\Core\Framework\Store\Services\ExtensionDownloader`
    * `Shopwell\Core\Framework\Store\Services\ExtensionLifecycleService`
    * `Shopwell\Core\Framework\Store\Services\ExtensionLoader`
    * `Shopwell\Core\Framework\Store\Services\ExtensionStoreLicensesService`
    * `Shopwell\Core\Framework\Store\Services\LicenseLoader`
    * `Shopwell\Core\Framework\Store\Services\StoreAppLifecycleService`
    * `Shopwell\Core\Framework\Store\Services\StoreCategoryProvider`
    * `Shopwell\Core\Framework\Store\Struct\BinaryCollection`
    * `Shopwell\Core\Framework\Store\Struct\BinaryStruct`
    * `Shopwell\Core\Framework\Store\Struct\CartPositionCollection`
    * `Shopwell\Core\Framework\Store\Struct\CartPositionStruct`
    * `Shopwell\Core\Framework\Store\Struct\CartStruct`
    * `Shopwell\Core\Framework\Store\Struct\DiscountCampaignStruct`
    * `Shopwell\Core\Framework\Store\Struct\ExtensionCollection`
    * `Shopwell\Core\Framework\Store\Struct\ExtensionStruct`
    * `Shopwell\Core\Framework\Store\Struct\FaqCollection`
    * `Shopwell\Core\Framework\Store\Struct\FaqStruct`
    * `Shopwell\Core\Framework\Store\Struct\ImageCollection`
    * `Shopwell\Core\Framework\Store\Struct\ImageStruct`
    * `Shopwell\Core\Framework\Store\Struct\LicenseCollection`
    * `Shopwell\Core\Framework\Store\Struct\LicenseStruct`
    * `Shopwell\Core\Framework\Store\Struct\PermissionCollection`
    * `Shopwell\Core\Framework\Store\Struct\PermissionStruct`
    * `Shopwell\Core\Framework\Store\Struct\ReviewCollection`
    * `Shopwell\Core\Framework\Store\Struct\ReviewStruct`
    * `Shopwell\Core\Framework\Store\Struct\ReviewSummaryStruct`
    * `Shopwell\Core\Framework\Store\Struct\StoreCategoryCollection`
    * `Shopwell\Core\Framework\Store\Struct\StoreCategoryStruct`
    * `Shopwell\Core\Framework\Store\Struct\StoreCollection`
    * `Shopwell\Core\Framework\Store\Struct\StoreStruct`
    * `Shopwell\Core\Framework\Store\Struct\VariantCollection`
    * `Shopwell\Core\Framework\Store\Struct\VariantStruct`
    * `Shopwell\Core\Framework\Test\Store\Api\ExtensionStoreActionsControllerTest`
    * `Shopwell\Core\Framework\Test\Store\Api\ExtensionStoreCategoryControllerTest`
    * `Shopwell\Core\Framework\Test\Store\Api\ExtensionStoreDataControllerTest`
    * `Shopwell\Core\Framework\Test\Store\Api\ExtensionStoreLicensesControllerTest`
    * `Shopwell\Core\Framework\Test\Store\Authentication\AuthenticationProviderTest`
    * `Shopwell\Core\Framework\Test\Store\Search\ExtensionCriteriaTest`
    * `Shopwell\Core\Framework\Test\Store\Search\FilterStructClassTest`
    * `Shopwell\Core\Framework\Test\Store\Service\ExtensionDataProviderTest`
    * `Shopwell\Core\Framework\Test\Store\Service\ExtensionDownloaderTest`
    * `Shopwell\Core\Framework\Test\Store\Service\ExtensionLifecycleServiceTest`
    * `Shopwell\Core\Framework\Test\Store\Service\ExtensionLoaderTest`
    * `Shopwell\Core\Framework\Test\Store\Service\ExtensionStoreLicensesServiceTest`
    * `Shopwell\Core\Framework\Test\Store\Service\LicenseLoaderTest`
    * `Swag\SaasRufus\Test\Core\Framework\Extension\Service\StoreCategoryProviderTest`
    * `Swag\SaasRufus\Test\Core\Framework\Extension\Struct\ExtensionStructTest`
    * `Swag\SaasRufus\Test\Core\Framework\Extension\Struct\PermissionCollectionTest`
    * `Swag\SaasRufus\Test\Core\Framework\Extension\Struct\ReviewStructTest`
    * `AppStoreTestPlugin\AppStoreTestPlugin`
* Added new method `Shopwell\Core\Framework\App\Lifecycle\AppLoader:deleteApp`
* Added new parameter `$type` to method `Shopwell\Core\Framework\Plugin\PluginExtractor:extract`
* Changed return value from method `Shopwell\Core\Framework\Plugin\PluginManagementService:extractPluginZip` from `void` to `string`
* Added new method `Shopwell\Core\Framework\Plugin\PluginZipDetector:isApp`
* Added new method `Shopwell\Core\Framework\Store\Api\StoreController:categoriesAction`
* Added new method `Shopwell\Core\Framework\Store\Services\StoreClient:getCategories`
* Added new method `Shopwell\Core\Framework\Store\Services\StoreClient:listExtensions`
* Added new method `Shopwell\Core\Framework\Store\Services\StoreClient:listListingFilters`
* Added new method `Shopwell\Core\Framework\Store\Services\StoreClient:extensionDetail`
* Added new method `Shopwell\Core\Framework\Store\Services\StoreClient:extensionDetailReviews`
* Added new method `Shopwell\Core\Framework\Store\Services\StoreClient:createCart`
* Added new method `Shopwell\Core\Framework\Store\Services\StoreClient:orderCart`
* Added new method `Shopwell\Core\Framework\Store\Services\StoreClient:cancelSubscription`
* Added new method `Shopwell\Core\Framework\Store\Services\StoreClient:createRating`
* Added new method `Shopwell\Core\Framework\Store\Services\StoreClient:getLicenses`
* Added new method `Shopwell\Core\Framework\Store\Services\StoreService:getLanguageByContext`
* Added new method `Shopwell\Core\Framework\Store\Struct\PluginDownloadDataStruct:getType`
