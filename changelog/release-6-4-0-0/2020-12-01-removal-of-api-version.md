---
title: Remove Api Version
issue: NEXT-10665
---

# Administration

* Deleted following file `src/Administration/Resources/app/administration/test/core/factory/http.factory.spec.js`
* Removed `context/setApiApiVersion` from `Shopwell.State`
* Removed `getApiVersion` from API Service
* Changed Cypress tests to consider `apiPath` env variable
___
# Core
___
* Removed parameter `$version` from method `Shopwell\Core\Checkout\Order\Api\OrderActionController:orderStateTransition`
* Removed parameter `$version` from method `Shopwell\Core\Checkout\Order\Api\OrderActionController:orderTransactionStateTransition`
* Removed parameter `$version` from method `Shopwell\Core\Checkout\Order\Api\OrderActionController:orderDeliveryStateTransition`
* Removed parameter `$version` from method `Shopwell\Core\Content\ImportExport\Controller\ImportExportActionController:initiate`
* Removed parameter `$version` from method `Shopwell\Core\Framework\Api\ApiDefinition\DefinitionService:generate`
* Removed parameter `$version` from method `Shopwell\Core\Framework\Api\ApiDefinition\DefinitionService:getSchema`
* Removed parameter `$version` from method `Shopwell\Core\Framework\Api\ApiDefinition\Generator\EntitySchemaGenerator:supports`
* Removed parameter `$version` from method `Shopwell\Core\Framework\Api\ApiDefinition\Generator\EntitySchemaGenerator:generate`
* Removed parameter `$version` from method `Shopwell\Core\Framework\Api\ApiDefinition\Generator\EntitySchemaGenerator:getSchema`
* Removed parameter `$version` from method `Shopwell\Core\Framework\Api\ApiDefinition\Generator\OpenApi\OpenApiDefinitionSchemaBuilder:getSchemaByDefinition`
* Removed parameter `$version` from method `Shopwell\Core\Framework\Api\ApiDefinition\Generator\OpenApi\OpenApiSchemaBuilder:enrich`
* Removed parameter `$version` from method `Shopwell\Core\Framework\Api\ApiDefinition\Generator\OpenApi3Generator:supports`
* Removed parameter `$version` from method `Shopwell\Core\Framework\Api\ApiDefinition\Generator\OpenApi3Generator:generate`
* Removed parameter `$version` from method `Shopwell\Core\Framework\Api\ApiDefinition\Generator\OpenApi3Generator:getSchema`
* Removed parameter `$version` from method `Shopwell\Core\Framework\Api\ApiDefinition\Generator\StoreApiGenerator:supports`
* Removed parameter `$version` from method `Shopwell\Core\Framework\Api\ApiDefinition\Generator\StoreApiGenerator:generate`
* Removed parameter `$version` from method `Shopwell\Core\Framework\Api\ApiDefinition\Generator\StoreApiGenerator:getSchema`
* Removed parameter `$version` from method `Shopwell\Core\Framework\Api\Controller\ApiController:compositeSearch`
* Removed parameter `$version` from method `Shopwell\Core\Framework\Api\Controller\ApiController:clone`
* Removed parameter `$version` from method `Shopwell\Core\Framework\Api\Controller\ApiController:createVersion`
* Removed parameter `$version` from method `Shopwell\Core\Framework\Api\Controller\ApiController:mergeVersion`
* Removed parameter `$version` from method `Shopwell\Core\Framework\Api\Controller\InfoController:info`
* Removed parameter `$version` from method `Shopwell\Core\Framework\Api\Controller\InfoController:openApiSchema`
* Removed parameter `$version` from method `Shopwell\Core\Framework\Api\Controller\InfoController:entitySchema`
* Removed parameter `$version` from method `Shopwell\Core\Framework\Api\Controller\InfoController:infoHtml`
* Removed parameter `$version` from method `Shopwell\Core\Framework\Api\Controller\SyncController:sync`
* Removed method `Shopwell\Core\Framework\Api\Converter\ApiConverter:getApiVersion`
* Removed method `Shopwell\Core\Framework\Api\Converter\ApiConverter:isDeprecated`
* Removed method `Shopwell\Core\Framework\Api\Converter\ApiConverter:isFromFuture`
* Removed method `Shopwell\Core\Framework\Api\Converter\ApiConverter:getDeprecations`
* Removed method `Shopwell\Core\Framework\Api\Converter\ApiConverter:getNewFields`
* Removed method `Shopwell\Core\Framework\Api\Converter\ApiVersionConverter:isAllowed`
* Removed parameter `$apiVersion` from method `Shopwell\Core\Framework\Api\Converter\ApiVersionConverter:convertEntity`
* Removed parameter `$apiVersion` from method `Shopwell\Core\Framework\Api\Converter\ApiVersionConverter:convertPayload`
* Removed method `Shopwell\Core\Framework\Api\Converter\ApiVersionConverter:validateEntityPath`
* Removed method `Shopwell\Core\Framework\Api\Converter\ApiVersionConverter:convertCriteria`
* Removed method `Shopwell\Core\Framework\Api\Converter\ApiVersionConverter:ignoreDeprecations`
* Removed method `Shopwell\Core\Framework\Api\Converter\ConverterRegistry:isDeprecated`
* Removed method `Shopwell\Core\Framework\Api\Converter\ConverterRegistry:isFromFuture`
* Removed parameter `$apiVersion` from method `Shopwell\Core\Framework\Api\Converter\ConverterRegistry:convert`
* Changed return value from method `Shopwell\Core\Framework\Api\Converter\ConverterRegistry:getConverters` from `array` to `iterable`
* Removed parameter `$apiVersion` from method `Shopwell\Core\Framework\Api\Converter\ConverterRegistry:getConverters`
* Removed parameter `$apiVersion` from method `Shopwell\Core\Framework\Api\Converter\DefaultApiConverter:convert`
* Removed parameter `$apiVersion` from method `Shopwell\Core\Framework\Api\Converter\DefaultApiConverter:isDeprecated`
* Removed parameter `$apiVersion` from method `Shopwell\Core\Framework\Api\Converter\DefaultApiConverter:getDeprecations`
* Removed method `Shopwell\Core\Framework\Api\Response\Type\JsonFactoryBase:getVersion`
* Removed parameter `$apiVersion` from method `Shopwell\Core\Framework\Api\Serializer\JsonApiEncoder:encode`
* Removed method `Shopwell\Core\Framework\Api\Serializer\JsonApiEncodingResult:getApiVersion`
* Removed parameter `$apiVersion` from method `Shopwell\Core\Framework\Api\Serializer\JsonEntityEncoder:encode`
* Removed method `Shopwell\Core\Framework\Api\Sync\SyncOperation:getApiVersion`
* Removed parameter `$apiVersion` from method `Shopwell\Core\Framework\DataAbstractionLayer\Search\CompositeEntitySearcher:search`
* Removed parameter `$version` from method `Shopwell\Core\Framework\Plugin\Api\PluginController:deletePlugin`
* Removed parameter `$version` from method `Shopwell\Core\Framework\Plugin\Api\PluginController:installPlugin`
* Removed parameter `$version` from method `Shopwell\Core\Framework\Plugin\Api\PluginController:uninstallPlugin`
* Removed parameter `$version` from method `Shopwell\Core\Framework\Plugin\Api\PluginController:activatePlugin`
* Removed parameter `$version` from method `Shopwell\Core\Framework\Plugin\Api\PluginController:deactivatePlugin`
* Removed method `Shopwell\Core\Framework\Test\Api\Sync\SyncServiceTest:testWriteDeprecatedFieldLeadsToError`
* Removed method `Shopwell\Core\Framework\Test\Api\Sync\SyncServiceTest:testWriteDeprecatedEntityLeadsToError`
* Removed parameter `$apiVersion` from method `Shopwell\Core\System\SalesChannel\Api\StructEncoder:encode`
* Removed parameter `$version` from method `Shopwell\Core\System\SalesChannel\SalesChannel\StoreApiInfoController:info`
* Removed parameter `$version` from method `Shopwell\Core\System\SalesChannel\SalesChannel\StoreApiInfoController:openApiSchema`
* Removed parameter `$version` from method `Shopwell\Core\System\SalesChannel\SalesChannel\StoreApiInfoController:infoHtml`
* Deleted following classes:
    * `Shopwell\Core\Framework\Api\ApiVersion\ApiVersionSubscriber`
    * `Shopwell\Core\Framework\Api\Converter\Exceptions\ApiConversionNotAllowedException`
    * `Shopwell\Core\Framework\Api\Converter\Exceptions\QueryFutureEntityException`
    * `Shopwell\Core\Framework\Api\Converter\Exceptions\QueryFutureFieldException`
    * `Shopwell\Core\Framework\Api\Converter\Exceptions\QueryRemovedEntityException`
    * `Shopwell\Core\Framework\Api\Converter\Exceptions\QueryRemovedFieldException`
    * `Shopwell\Core\Framework\Api\Converter\Exceptions\WriteFutureFieldException`
    * `Shopwell\Core\Framework\Api\Converter\Exceptions\WriteRemovedFieldException`
    * `Shopwell\Core\Framework\Test\Api\ApiVersion\ApiVersionSubscriberTest`
    * `Shopwell\Core\Framework\Test\Api\ApiVersioning\ApiVersioningV2Test`
    * `Shopwell\Core\Framework\Test\Api\ApiVersioning\ApiVersioningV3Test`
    * `Shopwell\Core\Framework\Test\Api\ApiVersioning\ApiVersioningV4Test`
    * `Shopwell\Core\Framework\Test\Api\ApiVersioning\fixtures\ApiConverter\ConverterV2`
    * `Shopwell\Core\Framework\Test\Api\ApiVersioning\fixtures\ApiConverter\ConverterV3`
    * `Shopwell\Core\Framework\Test\Api\ApiVersioning\fixtures\ApiConverter\ConverterV4`
    * `Shopwell\Core\Framework\Test\Api\ApiVersioning\fixtures\Entities\v1\BundleCollection`
    * `Shopwell\Core\Framework\Test\Api\ApiVersioning\fixtures\Entities\v1\BundleDefinition`
    * `Shopwell\Core\Framework\Test\Api\ApiVersioning\fixtures\Entities\v1\BundleEntity`
    * `Shopwell\Core\Framework\Test\Api\ApiVersioning\fixtures\Entities\v2\BundleCollection`
    * `Shopwell\Core\Framework\Test\Api\ApiVersioning\fixtures\Entities\v2\BundleDefinition`
    * `Shopwell\Core\Framework\Test\Api\ApiVersioning\fixtures\Entities\v2\BundleEntity`
    * `Shopwell\Core\Framework\Test\Api\ApiVersioning\fixtures\Entities\v3\Aggregate\BundlePrice\BundlePriceDefinition`
    * `Shopwell\Core\Framework\Test\Api\ApiVersioning\fixtures\Entities\v3\Aggregate\BundleTanslation\BundleTranslationDefinition`
    * `Shopwell\Core\Framework\Test\Api\ApiVersioning\fixtures\Entities\v3\BundleCollection`
    * `Shopwell\Core\Framework\Test\Api\ApiVersioning\fixtures\Entities\v3\BundleDefinition`
    * `Shopwell\Core\Framework\Test\Api\ApiVersioning\fixtures\Entities\v3\BundleEntity`
    * `Shopwell\Core\Framework\Test\Api\ApiVersioning\fixtures\Entities\v4\Aggregate\BundlePrice\BundlePriceDefinition`
    * `Shopwell\Core\Framework\Test\Api\ApiVersioning\fixtures\Entities\v4\Aggregate\BundleTanslation\BundleTranslationDefinition`
    * `Shopwell\Core\Framework\Test\Api\ApiVersioning\fixtures\Entities\v4\BundleCollection`
    * `Shopwell\Core\Framework\Test\Api\ApiVersioning\fixtures\Entities\v4\BundleDefinition`
    * `Shopwell\Core\Framework\Test\Api\ApiVersioning\fixtures\Entities\v4\BundleEntity`
    * `Shopwell\Core\Framework\Test\Api\ApiVersioning\fixtures\Migrations\Migration1571753490v1`
    * `Shopwell\Core\Framework\Test\Api\ApiVersioning\fixtures\Migrations\Migration1571754409v2`
    * `Shopwell\Core\Framework\Test\Api\ApiVersioning\fixtures\Migrations\Migration1571832058v3`
    * `Shopwell\Core\Framework\Test\Api\ApiVersioning\fixtures\Migrations\Migration1572528079v4`
    * `Shopwell\Core\Framework\Test\Api\Converter\ApiVersionConverterTest`
    * `Shopwell\Core\Framework\Test\Api\Converter\DefaultApiConverterTest`
    * `Shopwell\Core\Framework\Test\Api\Converter\fixtures\NewEntityDefinition`

