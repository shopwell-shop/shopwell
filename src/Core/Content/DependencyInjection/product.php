<?php declare(strict_types=1);

namespace Shopwell\Core\Content\DependencyInjection;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Shopwell\Core\Checkout\Cart\Facade\ScriptPriceStubs;
use Shopwell\Core\Checkout\Cart\Price\QuantityPriceCalculator;
use Shopwell\Core\Content\Category\Service\CategoryBreadcrumbBuilder;
use Shopwell\Core\Content\Cms\SalesChannel\SalesChannelCmsPageLoader;
use Shopwell\Core\Content\Cms\Service\EntityCmsSlotConfigInheritanceBuilder;
use Shopwell\Core\Content\MeasurementSystem\ProductMeasurement\ProductMeasurementUnitBuilder;
use Shopwell\Core\Content\MeasurementSystem\Unit\MeasurementUnitConverter;
use Shopwell\Core\Content\Media\UnusedMediaPurger;
use Shopwell\Core\Content\Product\AbstractIsNewDetector;
use Shopwell\Core\Content\Product\AbstractProductMaxPurchaseCalculator;
use Shopwell\Core\Content\Product\AbstractPropertyGroupSorter;
use Shopwell\Core\Content\Product\Aggregate\ProductCategory\ProductCategoryDefinition;
use Shopwell\Core\Content\Product\Aggregate\ProductCategoryTree\ProductCategoryTreeDefinition;
use Shopwell\Core\Content\Product\Aggregate\ProductConfiguratorSetting\ProductConfiguratorSettingDefinition;
use Shopwell\Core\Content\Product\Aggregate\ProductConfiguratorSetting\ProductConfiguratorSettingExceptionHandler;
use Shopwell\Core\Content\Product\Aggregate\ProductCrossSelling\ProductCrossSellingDefinition;
use Shopwell\Core\Content\Product\Aggregate\ProductCrossSellingAssignedProducts\ProductCrossSellingAssignedProductsDefinition;
use Shopwell\Core\Content\Product\Aggregate\ProductCrossSellingTranslation\ProductCrossSellingTranslationDefinition;
use Shopwell\Core\Content\Product\Aggregate\ProductCustomFieldSet\ProductCustomFieldSetDefinition;
use Shopwell\Core\Content\Product\Aggregate\ProductDownload\ProductDownloadDefinition;
use Shopwell\Core\Content\Product\Aggregate\ProductFeatureSet\ProductFeatureSetDefinition;
use Shopwell\Core\Content\Product\Aggregate\ProductFeatureSetTranslation\ProductFeatureSetTranslationDefinition;
use Shopwell\Core\Content\Product\Aggregate\ProductKeywordDictionary\ProductKeywordDictionaryDefinition;
use Shopwell\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopwell\Core\Content\Product\Aggregate\ProductManufacturerTranslation\ProductManufacturerTranslationDefinition;
use Shopwell\Core\Content\Product\Aggregate\ProductMedia\ProductMediaDefinition;
use Shopwell\Core\Content\Product\Aggregate\ProductOption\ProductOptionDefinition;
use Shopwell\Core\Content\Product\Aggregate\ProductPrice\ProductPriceDefinition;
use Shopwell\Core\Content\Product\Aggregate\ProductProperty\ProductPropertyDefinition;
use Shopwell\Core\Content\Product\Aggregate\ProductReview\ProductReviewDefinition;
use Shopwell\Core\Content\Product\Aggregate\ProductSearchConfig\ProductSearchConfigDefinition;
use Shopwell\Core\Content\Product\Aggregate\ProductSearchConfig\ProductSearchConfigExceptionHandler;
use Shopwell\Core\Content\Product\Aggregate\ProductSearchConfigField\ProductSearchConfigFieldDefinition;
use Shopwell\Core\Content\Product\Aggregate\ProductSearchConfigField\ProductSearchConfigFieldExceptionHandler;
use Shopwell\Core\Content\Product\Aggregate\ProductSearchKeyword\ProductSearchKeywordDefinition;
use Shopwell\Core\Content\Product\Aggregate\ProductStreamMapping\ProductStreamMappingDefinition;
use Shopwell\Core\Content\Product\Aggregate\ProductTag\ProductTagDefinition;
use Shopwell\Core\Content\Product\Aggregate\ProductTranslation\ProductTranslationDefinition;
use Shopwell\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopwell\Core\Content\Product\Api\ProductActionController;
use Shopwell\Core\Content\Product\Api\ProductNumberFkResolver;
use Shopwell\Core\Content\Product\Cart\ProductGateway;
use Shopwell\Core\Content\Product\Cart\ProductLineItemCommandValidator;
use Shopwell\Core\Content\Product\Cleanup\CleanupProductKeywordDictionaryTask;
use Shopwell\Core\Content\Product\Cleanup\CleanupProductKeywordDictionaryTaskHandler;
use Shopwell\Core\Content\Product\Cleanup\CleanupUnusedDownloadMediaTask;
use Shopwell\Core\Content\Product\Cleanup\CleanupUnusedDownloadMediaTaskHandler;
use Shopwell\Core\Content\Product\Cms\BuyBoxCmsElementResolver;
use Shopwell\Core\Content\Product\Cms\CrossSellingCmsElementResolver;
use Shopwell\Core\Content\Product\Cms\ManufacturerLogoCmsElementResolver;
use Shopwell\Core\Content\Product\Cms\ProductBoxCmsElementResolver;
use Shopwell\Core\Content\Product\Cms\ProductDescriptionReviewsCmsElementResolver;
use Shopwell\Core\Content\Product\Cms\ProductListingCmsElementResolver;
use Shopwell\Core\Content\Product\Cms\ProductNameCmsElementResolver;
use Shopwell\Core\Content\Product\Cms\ProductSlider\ProductStreamProcessor;
use Shopwell\Core\Content\Product\Cms\ProductSlider\StaticProductProcessor;
use Shopwell\Core\Content\Product\Cms\ProductSliderCmsElementResolver;
use Shopwell\Core\Content\Product\DataAbstractionLayer\CheapestPrice\CheapestPriceAccessorBuilder;
use Shopwell\Core\Content\Product\DataAbstractionLayer\CheapestPriceQuantitySelector;
use Shopwell\Core\Content\Product\DataAbstractionLayer\CheapestPriceUpdater;
use Shopwell\Core\Content\Product\DataAbstractionLayer\ProductCategoryDenormalizer;
use Shopwell\Core\Content\Product\DataAbstractionLayer\ProductDescriptionTeaserBuilder;
use Shopwell\Core\Content\Product\DataAbstractionLayer\ProductDescriptionTeaserIndexer;
use Shopwell\Core\Content\Product\DataAbstractionLayer\ProductExceptionHandler;
use Shopwell\Core\Content\Product\DataAbstractionLayer\ProductIndexer;
use Shopwell\Core\Content\Product\DataAbstractionLayer\ProductStreamUpdater;
use Shopwell\Core\Content\Product\DataAbstractionLayer\RatingAverageUpdater;
use Shopwell\Core\Content\Product\DataAbstractionLayer\SearchKeywordUpdater;
use Shopwell\Core\Content\Product\DataAbstractionLayer\StatesUpdater;
use Shopwell\Core\Content\Product\DataAbstractionLayer\StockUpdate\StockUpdateFilterProvider;
use Shopwell\Core\Content\Product\DataAbstractionLayer\VariantListingUpdater;
use Shopwell\Core\Content\Product\Garan\GaranLabelDurationFormatter;
use Shopwell\Core\Content\Product\Garan\GaranLabelInlineImage;
use Shopwell\Core\Content\Product\Garan\GaranLabelMailSubscriber;
use Shopwell\Core\Content\Product\Garan\GaranLabelProductValidator;
use Shopwell\Core\Content\Product\Garan\GaranLabelRenderer;
use Shopwell\Core\Content\Product\Garan\GaranLabelResolver;
use Shopwell\Core\Content\Product\Garan\GaranLabelTwigFilter;
use Shopwell\Core\Content\Product\IsNewDetector;
use Shopwell\Core\Content\Product\ProductDefinition;
use Shopwell\Core\Content\Product\ProductMaxPurchaseCalculator;
use Shopwell\Core\Content\Product\ProductTypeRegistry;
use Shopwell\Core\Content\Product\ProductVariationBuilder;
use Shopwell\Core\Content\Product\PropertyGroupSorter;
use Shopwell\Core\Content\Product\SalesChannel\CrossSelling\ProductCrossSellingRoute;
use Shopwell\Core\Content\Product\SalesChannel\Detail\AvailableCombinationLoader;
use Shopwell\Core\Content\Product\SalesChannel\Detail\ProductConfiguratorLoader;
use Shopwell\Core\Content\Product\SalesChannel\Detail\ProductDetailRoute;
use Shopwell\Core\Content\Product\SalesChannel\FindVariant\FindProductVariantRoute;
use Shopwell\Core\Content\Product\SalesChannel\Garan\AbstractGaranLabelRoute;
use Shopwell\Core\Content\Product\SalesChannel\Garan\GaranLabelRoute;
use Shopwell\Core\Content\Product\SalesChannel\Listing\Filter\AbstractListingFilterHandler;
use Shopwell\Core\Content\Product\SalesChannel\Listing\Filter\ManufacturerListingFilterHandler;
use Shopwell\Core\Content\Product\SalesChannel\Listing\Filter\PriceListingFilterHandler;
use Shopwell\Core\Content\Product\SalesChannel\Listing\Filter\PropertyListingFilterHandler;
use Shopwell\Core\Content\Product\SalesChannel\Listing\Filter\RatingListingFilterHandler;
use Shopwell\Core\Content\Product\SalesChannel\Listing\Filter\ShippingFreeListingFilterHandler;
use Shopwell\Core\Content\Product\SalesChannel\Listing\Processor\AggregationListingProcessor;
use Shopwell\Core\Content\Product\SalesChannel\Listing\Processor\AssociationLoadingListingProcessor;
use Shopwell\Core\Content\Product\SalesChannel\Listing\Processor\BehaviorListingProcessor;
use Shopwell\Core\Content\Product\SalesChannel\Listing\Processor\CompositeListingProcessor;
use Shopwell\Core\Content\Product\SalesChannel\Listing\Processor\CompressedCriteriaListingProcessor;
use Shopwell\Core\Content\Product\SalesChannel\Listing\Processor\PagingListingProcessor;
use Shopwell\Core\Content\Product\SalesChannel\Listing\Processor\SortingListingProcessor;
use Shopwell\Core\Content\Product\SalesChannel\Listing\ProductListingLoader;
use Shopwell\Core\Content\Product\SalesChannel\Listing\ProductListingRoute;
use Shopwell\Core\Content\Product\SalesChannel\Listing\ResolveCriteriaProductListingRoute;
use Shopwell\Core\Content\Product\SalesChannel\Price\AppScriptProductPriceCalculator;
use Shopwell\Core\Content\Product\SalesChannel\Price\ProductPriceCalculator;
use Shopwell\Core\Content\Product\SalesChannel\ProductCloseoutFilterFactory;
use Shopwell\Core\Content\Product\SalesChannel\ProductListRoute;
use Shopwell\Core\Content\Product\SalesChannel\PurchaseLimit\ProductPurchaseLimitRoute;
use Shopwell\Core\Content\Product\SalesChannel\Review\ProductReviewLoader;
use Shopwell\Core\Content\Product\SalesChannel\Review\ProductReviewRoute;
use Shopwell\Core\Content\Product\SalesChannel\Review\ProductReviewSaveRoute;
use Shopwell\Core\Content\Product\SalesChannel\SalesChannelProductDefinition;
use Shopwell\Core\Content\Product\SalesChannel\Search\ProductSearchRoute;
use Shopwell\Core\Content\Product\SalesChannel\Search\ResolvedCriteriaProductSearchRoute;
use Shopwell\Core\Content\Product\SalesChannel\Sorting\ProductSortingDefinition;
use Shopwell\Core\Content\Product\SalesChannel\Sorting\ProductSortingExceptionHandler;
use Shopwell\Core\Content\Product\SalesChannel\Sorting\ProductSortingTranslationDefinition;
use Shopwell\Core\Content\Product\SalesChannel\Suggest\ProductSuggestRoute;
use Shopwell\Core\Content\Product\SalesChannel\Suggest\ResolvedCriteriaProductSuggestRoute;
use Shopwell\Core\Content\Product\SearchKeyword\ProductSearchBuilder;
use Shopwell\Core\Content\Product\SearchKeyword\ProductSearchBuilderInterface;
use Shopwell\Core\Content\Product\SearchKeyword\ProductSearchKeywordAnalyzer;
use Shopwell\Core\Content\Product\SearchKeyword\ProductSearchTermInterpreter;
use Shopwell\Core\Content\Product\Stock\AvailableStockMirrorSubscriber;
use Shopwell\Core\Content\Product\Stock\LoadProductStockSubscriber;
use Shopwell\Core\Content\Product\Stock\OrderStockSubscriber;
use Shopwell\Core\Content\Product\Stock\StockStorage;
use Shopwell\Core\Content\Product\Subscriber\CustomFieldSearchableSubscriber;
use Shopwell\Core\Content\Product\Subscriber\ProductDescriptionTeaserSubscriber;
use Shopwell\Core\Content\Product\Subscriber\ProductSubscriber;
use Shopwell\Core\Content\Product\Subscriber\RepairDigitalProductStatesSubscriber;
use Shopwell\Core\Content\Product\Util\VariantCombinationLoader;
use Shopwell\Core\Content\ProductStream\Service\ProductStreamBuilder;
use Shopwell\Core\Content\Shared\MailFlow\DataProvider\ProductProvider;
use Shopwell\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopwell\Core\Framework\Adapter\Storage\AbstractKeyValueStorage;
use Shopwell\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopwell\Core\Framework\DataAbstractionLayer\Indexing\ChildCountUpdater;
use Shopwell\Core\Framework\DataAbstractionLayer\Indexing\InheritanceUpdater;
use Shopwell\Core\Framework\DataAbstractionLayer\Indexing\ManyToManyIdFieldUpdater;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\CompressedCriteriaDecoder;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\SearchConfigLoader;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Term\Filter\TokenFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Term\Tokenizer;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Script\Execution\ScriptExecutor;
use Shopwell\Core\Framework\Util\HtmlSanitizer;
use Shopwell\Core\Framework\Validation\DataValidator;
use Shopwell\Core\System\DeliveryTime\DeliveryTimeDefinition;
use Shopwell\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->instanceof(AbstractListingFilterHandler::class)
        ->tag('shopwell.listing.filter.handler');

    $services->set(ProductExceptionHandler::class)
        ->tag('shopwell.dal.exception_handler');

    $services->set(ProductSortingExceptionHandler::class)
        ->tag('shopwell.dal.exception_handler');

    $services->set(ProductDefinition::class)
        ->tag('shopwell.entity.definition')
        ->tag('shopwell.entity.hookable');

    $services->set(ProductStreamMappingDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(SalesChannelProductDefinition::class)
        ->tag('shopwell.sales_channel.entity.definition');

    $services->set(ProductCategoryDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(ProductCustomFieldSetDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(ProductTagDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(ProductConfiguratorSettingDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(ProductConfiguratorSettingExceptionHandler::class)
        ->tag('shopwell.dal.exception_handler');

    $services->set(ProductPriceDefinition::class)
        ->tag('shopwell.entity.definition')
        ->tag('shopwell.entity.hookable');

    $services->set(ProductPropertyDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(ProductSearchKeywordDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(ProductKeywordDictionaryDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(ProductReviewDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(ProductManufacturerDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(ProductManufacturerTranslationDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(ProductMediaDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(ProductDownloadDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(ProductTranslationDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(ProductOptionDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(ProductCategoryTreeDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(ProductCrossSellingDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(ProductCrossSellingTranslationDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(ProductCrossSellingAssignedProductsDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(ProductFeatureSetDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(ProductFeatureSetTranslationDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(ProductSortingDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(ProductSortingTranslationDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(ProductSearchConfigDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(ProductSearchConfigFieldDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(ProductSearchConfigFieldExceptionHandler::class)
        ->tag('shopwell.dal.exception_handler');

    $services->set(ProductSearchConfigExceptionHandler::class)
        ->tag('shopwell.dal.exception_handler');

    $services->set(ProductGateway::class)
        ->args([
            service('sales_channel.product.repository'),
            service('event_dispatcher'),
        ]);

    $services->set(AbstractPropertyGroupSorter::class, PropertyGroupSorter::class);

    $services->set(AbstractProductMaxPurchaseCalculator::class, ProductMaxPurchaseCalculator::class)
        ->args([
            service(SystemConfigService::class),
        ]);

    $services->set(AbstractIsNewDetector::class, IsNewDetector::class)
        ->args([
            service(SystemConfigService::class),
            service(ClockInterface::class),
        ]);

    $services->set(ProductVariationBuilder::class);

    $services->set(GaranLabelDurationFormatter::class);

    $services->set(GaranLabelRenderer::class)
        ->args([
            service('twig'),
        ]);

    $services->set(GaranLabelResolver::class)
        ->args([
            service(GaranLabelDurationFormatter::class),
            service(GaranLabelRenderer::class),
        ]);

    $services->set(GaranLabelTwigFilter::class)
        ->args([
            service(GaranLabelDurationFormatter::class),
            service('product.repository'),
            service(GaranLabelResolver::class),
            service(GaranLabelInlineImage::class),
        ])
        ->tag('twig.extension');

    $services->set(GaranLabelInlineImage::class);

    $services->set(GaranLabelMailSubscriber::class)
        ->args([
            service(GaranLabelInlineImage::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(GaranLabelProductValidator::class)
        ->tag('kernel.event_subscriber');

    $services->set(CustomFieldSearchableSubscriber::class)
        ->args([
            service(Connection::class),
            service('parameter_bag'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(RepairDigitalProductStatesSubscriber::class)
        ->args([
            service(Connection::class),
            service(StatesUpdater::class)->nullOnInvalid(),
            service(AbstractKeyValueStorage::class),
            service('logger'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(ProductSubscriber::class)
        ->args([
            service(ProductVariationBuilder::class),
            service(ProductPriceCalculator::class),
            service(AbstractPropertyGroupSorter::class),
            service(AbstractProductMaxPurchaseCalculator::class),
            service(AbstractIsNewDetector::class),
            service(SystemConfigService::class),
            service(ProductMeasurementUnitBuilder::class),
            service(MeasurementUnitConverter::class),
            service('request_stack'),
            service(Connection::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(ProductDescriptionTeaserBuilder::class)
        ->args([
            service(HtmlSanitizer::class),
        ]);

    $services->set(ProductDescriptionTeaserSubscriber::class)
        ->args([
            service(ProductDescriptionTeaserBuilder::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(ProductDescriptionTeaserIndexer::class)
        ->args([
            service(IteratorFactory::class),
            service(Connection::class),
            service(ProductDescriptionTeaserBuilder::class),
        ])
        ->tag('shopwell.entity_indexer');

    $services->set(OrderStockSubscriber::class)
        ->args([
            service(Connection::class),
            service(StockStorage::class),
            param('shopwell.stock.enable_stock_management'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(AvailableStockMirrorSubscriber::class)
        ->tag('kernel.event_listener');

    $services->set(LoadProductStockSubscriber::class)
        ->args([
            service(StockStorage::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(ProductSearchKeywordAnalyzer::class)
        ->args([
            service(Tokenizer::class),
            service(TokenFilter::class),
            service(SearchConfigLoader::class),
        ]);

    $services->set(ProductActionController::class)
        ->public()
        ->args([
            service(VariantCombinationLoader::class),
            service(ProductTypeRegistry::class),
        ])
        ->call('setContainer', [
            service('service_container'),
        ]);

    $services->set(ProductVisibilityDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(VariantCombinationLoader::class)
        ->args([
            service(Connection::class),
        ]);

    $services->set(DeliveryTimeDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(ProductBoxCmsElementResolver::class)
        ->args([
            service(SystemConfigService::class),
        ])
        ->tag('shopwell.cms.data_resolver');

    $services->set(ProductListingCmsElementResolver::class)
        ->args([
            service(ProductListingRoute::class),
            service('product_sorting.repository'),
        ])
        ->tag('shopwell.cms.data_resolver');

    $services->set(ProductSliderCmsElementResolver::class)
        ->args([
            tagged_iterator('shopwell.cms.product_slider.processor'),
            service('logger'),
        ])
        ->tag('shopwell.cms.data_resolver');

    $services->set(StaticProductProcessor::class)
        ->args([
            service(SystemConfigService::class),
            service('event_dispatcher'),
        ])
        ->tag('shopwell.cms.product_slider.processor');

    $services->set(ProductStreamProcessor::class)
        ->args([
            service(ProductStreamBuilder::class),
            service('sales_channel.product.repository'),
            service('event_dispatcher'),
            service('logger'),
            service(SystemConfigService::class),
            service(ProductCloseoutFilterFactory::class),
        ])
        ->tag('shopwell.cms.product_slider.processor');

    $services->set(ProductNameCmsElementResolver::class)
        ->tag('shopwell.cms.data_resolver');

    $services->set(ManufacturerLogoCmsElementResolver::class)
        ->tag('shopwell.cms.data_resolver');

    $services->set(CrossSellingCmsElementResolver::class)
        ->args([
            service(ProductCrossSellingRoute::class),
        ])
        ->tag('shopwell.cms.data_resolver');

    $services->set(ProductDescriptionReviewsCmsElementResolver::class)
        ->args([
            service(ProductReviewLoader::class),
            service(ScriptExecutor::class),
            service(SystemConfigService::class),
        ])
        ->tag('shopwell.cms.data_resolver');

    $services->set(ProductPriceCalculator::class)
        ->args([
            service('unit.repository'),
            service(QuantityPriceCalculator::class),
            service(ExtensionDispatcher::class),
        ])
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(AppScriptProductPriceCalculator::class)
        ->decorate(ProductPriceCalculator::class)
        ->args([
            service(AppScriptProductPriceCalculator::class . '.inner'),
            service(ScriptExecutor::class),
            service(ScriptPriceStubs::class),
        ])
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(CompositeListingProcessor::class)
        ->args([
            tagged_iterator('shopwell.listing.processor'),
        ]);

    $services->set(CompressedCriteriaListingProcessor::class)
        ->args([
            service(CompressedCriteriaDecoder::class),
        ])
        // priority needs to be higher than of all other processors to make custom filters passed in compressed criteria to work
        ->tag('shopwell.listing.processor', ['priority' => 1000]);

    $services->set(ManufacturerListingFilterHandler::class);

    $services->set(PriceListingFilterHandler::class);

    $services->set(RatingListingFilterHandler::class);

    $services->set(ShippingFreeListingFilterHandler::class);

    $services->set(PropertyListingFilterHandler::class)
        ->args([
            service('property_group.repository'),
            service('property_group_option.repository'),
            service(Connection::class),
        ]);

    $services->set(SortingListingProcessor::class)
        ->args([
            service(SystemConfigService::class),
            service('product_sorting.repository'),
            service('event_dispatcher'),
        ])
        ->tag('shopwell.listing.processor');

    $services->set(AggregationListingProcessor::class)
        ->args([
            tagged_iterator('shopwell.listing.filter.handler'),
            service('event_dispatcher'),
        ])
        ->tag('shopwell.listing.processor');

    $services->set(AssociationLoadingListingProcessor::class)
        ->tag('shopwell.listing.processor');

    $services->set(BehaviorListingProcessor::class)
        ->tag('shopwell.listing.processor', ['priority' => -1000]);

    $services->set(PagingListingProcessor::class)
        ->args([
            service(SystemConfigService::class),
            param('shopwell.api.store.max_limit'),
        ])
        ->tag('shopwell.listing.processor');

    $services->set(ProductSearchBuilderInterface::class, ProductSearchBuilder::class)
        ->args([
            service(ProductSearchTermInterpreter::class),
            service('logger'),
            param('shopwell.search.term_max_length'),
            param('shopwell.product.search_keyword.indexing'),
        ]);

    $services->set(ProductLineItemCommandValidator::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(ResolvedCriteriaProductSuggestRoute::class)
        ->decorate(ProductSuggestRoute::class, null, -2000)
        ->public()
        ->args([
            service(ProductSearchBuilderInterface::class),
            service('event_dispatcher'),
            service(ResolvedCriteriaProductSuggestRoute::class . '.inner'),
            service(CompositeListingProcessor::class),
        ]);

    $services->set(ProductSuggestRoute::class)
        ->public()
        ->args([
            service(ProductListingLoader::class),
        ]);

    $services->set(ProductSearchRoute::class)
        ->public()
        ->args([
            service(ProductSearchBuilderInterface::class),
            service(ProductListingLoader::class),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(ResolvedCriteriaProductSearchRoute::class)
        ->decorate(ProductSearchRoute::class, null, -2000)
        ->public()
        ->args([
            service(ResolvedCriteriaProductSearchRoute::class . '.inner'),
            service('event_dispatcher'),
            service(CompositeListingProcessor::class),
        ]);

    $services->set(ResolveCriteriaProductListingRoute::class)
        ->decorate(ProductListingRoute::class, null, -2000)
        ->public()
        ->args([
            service(ResolveCriteriaProductListingRoute::class . '.inner'),
            service('event_dispatcher'),
            service(CompositeListingProcessor::class),
        ]);

    $services->set(FindProductVariantRoute::class)
        ->public()
        ->args([
            service('sales_channel.product.repository'),
            service(CacheTagCollector::class),
            service(SystemConfigService::class),
            service(ProductCloseoutFilterFactory::class),
        ]);

    // decorated by cached route
    $services->set(ProductListingRoute::class)
        ->public()
        ->args([
            service(ProductListingLoader::class),
            service('category.repository'),
            service(ProductStreamBuilder::class),
            service(CacheTagCollector::class),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(ProductIndexer::class)
        ->args([
            service(IteratorFactory::class),
            service('product.repository'),
            service(Connection::class),
            service(VariantListingUpdater::class),
            service(ProductCategoryDenormalizer::class),
            service(InheritanceUpdater::class),
            service(RatingAverageUpdater::class),
            service(SearchKeywordUpdater::class),
            service(ChildCountUpdater::class),
            service(ManyToManyIdFieldUpdater::class),
            service(StockStorage::class),
            service('event_dispatcher'),
            service(CheapestPriceUpdater::class),
            service(ProductStreamUpdater::class),
            service('messenger.default_bus'),
            service(StatesUpdater::class)->nullOnInvalid(),
            service(ClockInterface::class),
        ])
        ->tag('shopwell.entity_indexer', ['priority' => 100]);

    $services->set(ProductStreamUpdater::class)
        ->args([
            service(Connection::class),
            service(ProductDefinition::class),
            service('product.repository'),
            service('messenger.default_bus'),
            service(ManyToManyIdFieldUpdater::class),
            service('language.repository'),
            param('shopwell.product_stream.indexing'),
        ])
        ->tag('shopwell.entity_indexer');

    $services->set(ProductTypeRegistry::class)
        ->public()
        ->args([
            param('shopwell.product.allowed_types'),
        ])
        ->tag('shopwell.api.enum_provider');

    $services->set(StatesUpdater::class)
        ->args([
            service(Connection::class),
            service('event_dispatcher'),
        ]);

    $services->set(VariantListingUpdater::class)
        ->args([
            service(Connection::class),
        ]);

    $services->set(ProductCategoryDenormalizer::class)
        ->args([
            service(Connection::class),
        ]);

    $services->set(CheapestPriceUpdater::class)
        ->args([
            service(Connection::class),
            service(CheapestPriceQuantitySelector::class),
            service('event_dispatcher'),
        ]);

    $services->set(CheapestPriceQuantitySelector::class);

    $services->set(RatingAverageUpdater::class)
        ->args([
            service(Connection::class),
        ]);

    $services->set(SearchKeywordUpdater::class)
        ->args([
            service(Connection::class),
            service('language.repository'),
            service('product.repository'),
            service(ProductSearchKeywordAnalyzer::class),
            service(ClockInterface::class),
            param('shopwell.product.search_keyword.indexing'),
        ])
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(StockUpdateFilterProvider::class)
        ->args([
            tagged_iterator('shopwell.product.stock_filter'),
        ]);

    $services->set(StockStorage::class)
        ->args([
            service(Connection::class),
            service('event_dispatcher'),
        ]);

    $services->set(ProductListingLoader::class)
        ->args([
            service('sales_channel.product.repository'),
            service(SystemConfigService::class),
            service(Connection::class),
            service('event_dispatcher'),
            service(ProductCloseoutFilterFactory::class),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(ProductDetailRoute::class)
        ->public()
        ->args([
            service('sales_channel.product.repository'),
            service('product_translation.repository'),
            service(SystemConfigService::class),
            service(Connection::class),
            service(ProductConfiguratorLoader::class),
            service(CategoryBreadcrumbBuilder::class),
            service(SalesChannelCmsPageLoader::class),
            service(EntityCmsSlotConfigInheritanceBuilder::class),
            service(SalesChannelProductDefinition::class),
            service(ProductCloseoutFilterFactory::class),
            service('event_dispatcher'),
            service(CacheTagCollector::class),
        ]);

    $services->set(ProductPurchaseLimitRoute::class)
        ->public()
        ->args([
            service('sales_channel.product.repository'),
            service(AbstractProductMaxPurchaseCalculator::class),
        ]);

    $services->set(ProductReviewLoader::class)
        ->args([
            service(ProductReviewRoute::class),
            service(SystemConfigService::class),
            service('event_dispatcher'),
        ]);

    $services->set(ProductReviewRoute::class)
        ->public()
        ->args([
            service('product_review.repository'),
            service(SystemConfigService::class),
            service(CacheTagCollector::class),
        ]);

    $services->set(ProductConfiguratorLoader::class)
        ->args([
            service(AvailableCombinationLoader::class),
            service('property_group_option.repository'),
        ]);

    $services->set(AvailableCombinationLoader::class)
        ->args([
            service(Connection::class),
            service(StockStorage::class),
            service(SystemConfigService::class),
        ]);

    $services->set(ProductCrossSellingRoute::class)
        ->public()
        ->args([
            service('product_cross_selling.repository'),
            service('event_dispatcher'),
            service(ProductStreamBuilder::class),
            service('sales_channel.product.repository'),
            service(SystemConfigService::class),
            service(ProductListingLoader::class),
            service(ProductCloseoutFilterFactory::class),
            service(CacheTagCollector::class),
            service(Connection::class),
        ]);

    $services->set(ProductReviewSaveRoute::class)
        ->public()
        ->args([
            service('product_review.repository'),
            service(DataValidator::class),
            service(SystemConfigService::class),
            service('event_dispatcher'),
            service(ProductProvider::class),
        ]);

    $services->set(ProductListRoute::class)
        ->public()
        ->args([
            service('sales_channel.product.repository'),
        ]);

    $services->set(GaranLabelRoute::class)
        ->public()
        ->args([
            service('sales_channel.product.repository'),
            service(GaranLabelResolver::class),
        ]);

    $services->alias(AbstractGaranLabelRoute::class, GaranLabelRoute::class);

    $services->set(BuyBoxCmsElementResolver::class)
        ->args([
            service(ProductConfiguratorLoader::class),
            service('product_review.repository'),
        ])
        ->tag('shopwell.cms.data_resolver');

    $services->set(TokenFilter::class)
        ->args([
            service(SearchConfigLoader::class),
        ])
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(CheapestPriceAccessorBuilder::class)
        ->args([
            param('shopwell.dal.max_rule_prices'),
            service('logger'),
        ])
        ->tag('shopwell.field_accessor_builder', ['priority' => -200]);

    $services->set(CleanupProductKeywordDictionaryTask::class)
        ->tag('shopwell.scheduled.task');

    $services->set(CleanupProductKeywordDictionaryTaskHandler::class)
        ->args([
            service('scheduled_task.repository'),
            service('logger'),
            service(Connection::class),
        ])
        ->tag('messenger.message_handler');

    $services->set(CleanupUnusedDownloadMediaTask::class)
        ->tag('shopwell.scheduled.task');

    $services->set(CleanupUnusedDownloadMediaTaskHandler::class)
        ->args([
            service('scheduled_task.repository'),
            service('logger'),
            service(UnusedMediaPurger::class),
        ])
        ->tag('messenger.message_handler');

    $services->set(ProductCloseoutFilterFactory::class);

    $services->set(ProductNumberFkResolver::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('shopwell.sync.fk_resolver');
};
