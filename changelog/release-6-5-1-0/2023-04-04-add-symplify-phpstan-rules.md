---
title: Add symplify phpstan rules
issue: NEXT-25940
---
# Core
* Added symplify/phpstan-rules to the dev dependencies and activated some new rules for the CI.
* Deprecated class `\Shopwell\Core\Framework\Adapter\Filesystem\Filesystem` it will be removed in v6.6.0.0 as it was unused.
* Deprecated class `\Shopwell\Core\Framework\Struct\Serializer\StructDecoder` it will be removed in v6.6.0.0 as it was unused.
* Deprecated properties `id`, `name` and `quantity` in `\Shopwell\Core\Content\Product\Cart\PurchaseStepsError` and `\Shopwell\Core\Content\Product\Cart\ProductStockReachedError`, the properties will become private and natively typed in v6.6.0.0.
* Deprecated properties `redis` and `connection` in `\Shopwell\Core\Checkout\Cart\Command\CartMigrateCommand`, those will become private and readonly in v6.6.0.0.
___
# Upgrade Information
## Fix method signatures to comply with parent class/interface signature
The following method signatures were changed to comply with the parent class/interface signature:
**Visibility changes:**
* Method `configure()` was changed from public to protected in:
  * `Shopwell\Storefront\Theme\Command\ThemeCompileCommand`
* Method `execute()` was changed from public to protected in:
  * `Shopwell\Core\Framework\Adapter\Asset\AssetInstallCommand`
  * `Shopwell\Core\DevOps\System\Command\SystemDumpDatabaseCommand`
  * `Shopwell\Core\DevOps\System\Command\SystemRestoreDatabaseCommand`
  * `Shopwell\Core\DevOps\Docs\App\DocsAppEventCommand`
  * 
* Method `getExpectedClass()` was changed from public to protected in:
  * `Shopwell\Storefront\Theme\ThemeSalesChannelCollection`
  * `Shopwell\Core\Framework\Store\Struct\PluginRecommendationCollection`
  * `Shopwell\Core\Framework\Store\Struct\PluginCategoryCollection`
  * `Shopwell\Core\Framework\Store\Struct\LicenseDomainCollection`
  * `Shopwell\Core\Framework\Store\Struct\PluginRegionCollection`
  * `Shopwell\Core\Content\ImportExport\Processing\Mapping\UpdateByCollection`
  * `Shopwell\Core\Content\ImportExport\Processing\Mapping\MappingCollection`
  * `Shopwell\Core\Content\Product\Aggregate\ProductCrossSellingAssignedProducts\ProductCrossSellingAssignedProductsCollection`
  * `Shopwell\Core\Content\Product\Aggregate\ProductCrossSelling\ProductCrossSellingCollection`
  * `Shopwell\Core\Content\Product\SalesChannel\CrossSelling\CrossSellingElementCollection`
  * `Shopwell\Core\Content\Product\SalesChannel\SalesChannelProductCollection`
  * `Shopwell\Core\Checkout\Promotion\Aggregate\PromotionDiscountPrice\PromotionDiscountPriceCollection`
* Method `getParentDefinitionClass()` was changed from public to protected in:
  * `Shopwell\Core\System\SalesChannel\Aggregate\SalesChannelAnalytics\SalesChannelAnalyticsDefinition`
  * `Shopwell\Core\Content\ImportExport\ImportExportProfileTranslationDefinition`
  * `Shopwell\Core\Content\Product\Aggregate\ProductCrossSellingAssignedProducts\ProductCrossSellingAssignedProductsDefinition`
  * `Shopwell\Core\Content\Product\Aggregate\ProductCrossSelling\ProductCrossSellingDefinition`
  * `Shopwell\Core\Content\Product\Aggregate\ProductFeatureSetTranslation\ProductFeatureSetTranslationDefinition`
  * `Shopwell\Core\Checkout\Promotion\Aggregate\PromotionTranslation\PromotionTranslationDefinition`
* Method `getDecorated()` was changed from public to protected in:
  * `Shopwell\Core\System\Country\SalesChannel\CachedCountryRoute`
  * `Shopwell\Core\System\Country\SalesChannel\CachedCountryStateRoute`
* Method `getSerializerClass()` was changed from public to protected in:
  * `Shopwell\Core\Framework\DataAbstractionLayer\Field\StateMachineStateField`

**Parameter type changes:**
* Changed parameter `$url` to `string` in:
  * `Shopwell\Storefront\Framework\Cache\ReverseProxy\ReverseProxyCache#purge()`
* Changed parameter `$data` and `$format` to `string` in:
  * `Shopwell\Core\Framework\Struct\Serializer\StructDecoder#decode()`
  * `Shopwell\Core\Framework\Struct\Serializer\StructDecoder#supportsDecoding()`
  * `Shopwell\Core\Framework\Api\Serializer\JsonApiDecoder#decode()`
  * `Shopwell\Core\Framework\Api\Serializer\JsonApiDecoder#supportsDecoding()`
* Changed parameter `$storageName` and `$propertyName` to `string` in:
  * `Shopwell\Core\Framework\DataAbstractionLayer\Field\CustomFields#__construct()`
* Changed parameter `$event` to `object` in:
  * `Shopwell\Core\Framework\Event\NestedEventDispatcher#dispatch()`
* Changed parameter `$listener` to `callable` in:
  * `Shopwell\Core\Framework\Event\NestedEventDispatcher#removeListener()`
  * `Shopwell\Core\Framework\Event\NestedEventDispatcher#getListenerPriority()`
  * `Shopwell\Core\Framework\Webhook\WebhookDispatcher#removeListener()`
  * `Shopwell\Core\Framework\Webhook\WebhookDispatcher#getListenerPriority()`
* Changed parameter `$constraints` to `Symfony\Component\Validator\Constraint|array|null` in:
  * `Shopwell\Core\Framework\Validation\HappyPathValidator#validate()`
* Changed parameter `$object` to `object`, `$propertyName` to `string`, `$groups` to `string|Symfony\Component\Validator\Constraints\GroupSequence|array|null` and `$objectOrClass` to `object|string` in:
  * `Shopwell\Core\Framework\Validation\HappyPathValidator#validateProperty()`
  * `Shopwell\Core\Framework\Validation\HappyPathValidator#validatePropertyValue()`
* Changed parameter `$record` to `iterable` in:
  * `Shopwell\Core\Content\ImportExport\Processing\Pipe\EntityPipe#in()`
* Changed parameter `$warmupDir` to `string` in:
  * `Shopwell\Core\Kernel#reboot()`

