<?php declare(strict_types=1);

use League\OAuth2\Server\Entities\ScopeEntityInterface;
use Shopwell\Core\Checkout\Cart\CartDataCollectorInterface;
use Shopwell\Core\Checkout\Cart\CartProcessorInterface;
use Shopwell\Core\Checkout\Cart\CartValidatorInterface;
use Shopwell\Core\Checkout\Cart\LineItem\Group\LineItemGroupPackagerInterface;
use Shopwell\Core\Checkout\Cart\LineItem\Group\LineItemGroupSorterInterface;
use Shopwell\Core\Checkout\Cart\LineItemFactoryHandler\LineItemFactoryInterface;
use Shopwell\Core\Checkout\Cart\TaxProvider\AbstractTaxProvider;
use Shopwell\Core\Checkout\Customer\Password\LegacyEncoder\LegacyEncoderInterface;
use Shopwell\Core\Checkout\Document\Renderer\AbstractDocumentRenderer;
use Shopwell\Core\Checkout\Document\Service\AbstractDocumentTypeRenderer;
use Shopwell\Core\Checkout\DocumentV2\Provider\AbstractDocumentDataProvider;
use Shopwell\Core\Checkout\DocumentV2\Type\AbstractDocumentType;
use Shopwell\Core\Checkout\Gateway\Command\Handler\AbstractCheckoutGatewayCommandHandler;
use Shopwell\Core\Checkout\Payment\Cart\PaymentHandler\AbstractPaymentHandler;
use Shopwell\Core\Checkout\Promotion\Cart\Discount\Filter\FilterPickerInterface;
use Shopwell\Core\Checkout\Promotion\Cart\Discount\Filter\FilterSorterInterface;
use Shopwell\Core\Content\Cms\DataResolver\Element\CmsElementResolverInterface;
use Shopwell\Core\Content\Flow\Dispatching\Action\FlowAction;
use Shopwell\Core\Content\Flow\Dispatching\Storer\FlowStorer;
use Shopwell\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity\AbstractEntitySerializer;
use Shopwell\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Field\AbstractFieldSerializer;
use Shopwell\Core\Content\ImportExport\Processing\Reader\AbstractReaderFactory;
use Shopwell\Core\Content\ImportExport\Processing\Writer\AbstractWriterFactory;
use Shopwell\Core\Content\Media\Core\Application\AbstractMediaPathStrategy;
use Shopwell\Core\Content\Media\File\AbstractFileContentValidator;
use Shopwell\Core\Content\Media\Metadata\MetadataLoader\MetadataLoaderInterface;
use Shopwell\Core\Content\Media\TypeDetector\TypeDetectorInterface;
use Shopwell\Core\Content\Product\Cms\ProductSlider\AbstractProductSliderProcessor;
use Shopwell\Core\Content\Product\DataAbstractionLayer\StockUpdate\AbstractStockUpdateFilter;
use Shopwell\Core\Content\Product\SalesChannel\Listing\Filter\AbstractListingFilterHandler;
use Shopwell\Core\Content\Product\SalesChannel\Listing\Processor\AbstractListingProcessor;
use Shopwell\Core\Content\ProductExport\Provider\AbstractAgenticCommerceProductExportProvider;
use Shopwell\Core\Content\ProductExport\Validator\ValidatorInterface;
use Shopwell\Core\Content\Seo\SeoUrlRoute\EntitySeoUrlRouteInterface;
use Shopwell\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteInterface;
use Shopwell\Core\Content\Shared\MailFlow\DataProvider\MailFlowDataProviderInterface;
use Shopwell\Core\Content\Sitemap\ConfigHandler\ConfigHandlerInterface;
use Shopwell\Core\Content\Sitemap\Provider\AbstractUrlProvider;
use Shopwell\Core\Framework\Adapter\Filesystem\Adapter\AdapterFactoryInterface;
use Shopwell\Core\Framework\Adapter\Twig\NamespaceHierarchy\TemplateNamespaceHierarchyBuilderInterface;
use Shopwell\Core\Framework\Api\Cors\CorsHeaderProviderInterface;
use Shopwell\Core\Framework\Api\Sync\AbstractFkResolver;
use Shopwell\Core\Framework\DataAbstractionLayer\Dbal\ExceptionHandlerInterface;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\FieldEnumProviderInterface;
use Shopwell\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer;
use Shopwell\Core\Framework\Demodata\DemodataGeneratorInterface;
use Shopwell\Core\Framework\Increment\AbstractIncrementer;
use Shopwell\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;
use Shopwell\Core\Framework\Routing\AbstractRouteScope;
use Shopwell\Core\Framework\Routing\RouteScopeWhitelistInterface;
use Shopwell\Core\Framework\Rule\Rule;
use Shopwell\Core\Framework\SystemCheck\BaseCheck;
use Shopwell\Core\Framework\Telemetry\Metrics\Metric\PeriodicMetricCollectorInterface;
use Shopwell\Core\Framework\Telemetry\Metrics\MetricTransportInterface;
use Shopwell\Core\System\NumberRange\ValueGenerator\Pattern\AbstractValueGenerator;
use Shopwell\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage\AbstractIncrementStorage;
use Shopwell\Core\System\SalesChannel\Capability\AbstractSalesChannelTypeCapabilities;
use Shopwell\Core\System\Snippet\Filter\SnippetFilterInterface;
use Shopwell\Core\System\Tax\TaxRuleType\TaxRuleTypeFilterInterface;
use Shopwell\Elasticsearch\Admin\Indexer\AbstractAdminIndexer;
use Shopwell\Elasticsearch\Framework\AbstractElasticsearchDefinition;
use Shopwell\Storefront\Framework\Captcha\AbstractCaptcha;
use Shopwell\Storefront\Framework\Media\StorefrontMediaValidatorInterface;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Twig\Extension\ExtensionInterface;

return [
    'parameters' => [
        // Changing a mapped contract class is a backward compatibility break and must not be done in a minor release.
        'shopwellTaggedServiceContractTagContracts' => [
            'document.renderer' => AbstractDocumentRenderer::class,
            'document_type.renderer' => AbstractDocumentTypeRenderer::class,
            'flow.action' => FlowAction::class,
            'flow.storer' => FlowStorer::class,
            'lineitem.group.packager' => LineItemGroupPackagerInterface::class,
            'lineitem.group.sorter' => LineItemGroupSorterInterface::class,
            'messenger.receiver' => ReceiverInterface::class,
            'promotion.filter.picker' => FilterPickerInterface::class,
            'promotion.filter.sorter' => FilterSorterInterface::class,
            'shopwell.api.cors_header_provider' => CorsHeaderProviderInterface::class,
            'shopwell.api.enum_provider' => FieldEnumProviderInterface::class,
            'shopwell.app_script.twig.extension' => ExtensionInterface::class,
            'shopwell.cart.collector' => CartDataCollectorInterface::class,
            'shopwell.cart.line_item.factory' => LineItemFactoryInterface::class,
            'shopwell.cart.processor' => CartProcessorInterface::class,
            'shopwell.cart.validator' => CartValidatorInterface::class,
            'shopwell.checkout.gateway.command' => AbstractCheckoutGatewayCommandHandler::class,
            'shopwell.cms.data_resolver' => CmsElementResolverInterface::class,
            'shopwell.cms.product_slider.processor' => AbstractProductSliderProcessor::class,
            'shopwell.dal.exception_handler' => ExceptionHandlerInterface::class,
            'shopwell.demodata_generator' => DemodataGeneratorInterface::class,
            'shopwell.document_v2.provider' => AbstractDocumentDataProvider::class,
            'shopwell.document_v2.renderer' => Shopwell\Core\Checkout\DocumentV2\Renderer\AbstractDocumentRenderer::class,
            'shopwell.document_v2.type' => AbstractDocumentType::class,
            'shopwell.elastic.admin-searcher-index' => AbstractAdminIndexer::class,
            'shopwell.entity.definition' => EntityDefinition::class,
            'shopwell.entity.hookable' => [EntityDefinition::class, Entity::class],
            'shopwell.entity.seo_url.route' => EntitySeoUrlRouteInterface::class,
            'shopwell.entity_indexer' => EntityIndexer::class,
            'shopwell.es.definition' => AbstractElasticsearchDefinition::class,
            'shopwell.filesystem.factory' => AdapterFactoryInterface::class,
            'shopwell.import_export.entity_serializer' => AbstractEntitySerializer::class,
            'shopwell.import_export.field_serializer' => AbstractFieldSerializer::class,
            'shopwell.import_export.reader_factory' => AbstractReaderFactory::class,
            'shopwell.import_export.writer_factory' => AbstractWriterFactory::class,
            'shopwell.increment.gateway' => AbstractIncrementer::class,
            'shopwell.legacy_encoder' => LegacyEncoderInterface::class,
            'shopwell.listing.filter.handler' => AbstractListingFilterHandler::class,
            'shopwell.listing.processor' => AbstractListingProcessor::class,
            'shopwell.mail.data_provider' => MailFlowDataProviderInterface::class,
            'shopwell.media.file_content.validator' => AbstractFileContentValidator::class,
            'shopwell.media_type.detector' => TypeDetectorInterface::class,
            'shopwell.metadata.loader' => MetadataLoaderInterface::class,
            'shopwell.metric_transport_factory' => MetricTransportInterface::class,
            'shopwell.oauth.scope' => ScopeEntityInterface::class,
            'shopwell.path.strategy' => AbstractMediaPathStrategy::class,
            'shopwell.payment.method' => AbstractPaymentHandler::class,
            'shopwell.product.stock_filter' => AbstractStockUpdateFilter::class,
            'shopwell.product_export.provider' => AbstractAgenticCommerceProductExportProvider::class,
            'shopwell.product_export.validator' => ValidatorInterface::class,
            'shopwell.route_scope' => AbstractRouteScope::class,
            'shopwell.route_scope_whitelist' => RouteScopeWhitelistInterface::class,
            'shopwell.rule.definition' => Rule::class,
            'shopwell.sales_channel.type_capabilities' => AbstractSalesChannelTypeCapabilities::class,
            'shopwell.scheduled.task' => ScheduledTask::class,
            'shopwell.seo_url.route' => SeoUrlRouteInterface::class,
            'shopwell.sitemap.config_handler' => ConfigHandlerInterface::class,
            'shopwell.sitemap_url_provider' => AbstractUrlProvider::class,
            'shopwell.snippet.filter' => SnippetFilterInterface::class,
            'shopwell.storefront.captcha' => AbstractCaptcha::class,
            'shopwell.sync.fk_resolver' => AbstractFkResolver::class,
            'shopwell.system_check' => BaseCheck::class,
            'shopwell.tax.provider' => AbstractTaxProvider::class,
            'shopwell.telemetry.periodic_metric_collector' => PeriodicMetricCollectorInterface::class,
            'shopwell.twig.hierarchy_builder' => TemplateNamespaceHierarchyBuilderInterface::class,
            'shopwell.value_generator_connector' => AbstractIncrementStorage::class,
            'shopwell.value_generator_pattern' => AbstractValueGenerator::class,
            'storefront.media.upload.validator' => StorefrontMediaValidatorInterface::class,
            'tax.rule_type_filter' => TaxRuleTypeFilterInterface::class,
        ],
    ],
];
