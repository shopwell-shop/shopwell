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
        'shopwareTaggedServiceContractTagContracts' => [
            'document.renderer' => AbstractDocumentRenderer::class,
            'document_type.renderer' => AbstractDocumentTypeRenderer::class,
            'flow.action' => FlowAction::class,
            'flow.storer' => FlowStorer::class,
            'lineitem.group.packager' => LineItemGroupPackagerInterface::class,
            'lineitem.group.sorter' => LineItemGroupSorterInterface::class,
            'messenger.receiver' => ReceiverInterface::class,
            'promotion.filter.picker' => FilterPickerInterface::class,
            'promotion.filter.sorter' => FilterSorterInterface::class,
            'shopware.api.cors_header_provider' => CorsHeaderProviderInterface::class,
            'shopware.api.enum_provider' => FieldEnumProviderInterface::class,
            'shopware.app_script.twig.extension' => ExtensionInterface::class,
            'shopware.cart.collector' => CartDataCollectorInterface::class,
            'shopware.cart.line_item.factory' => LineItemFactoryInterface::class,
            'shopware.cart.processor' => CartProcessorInterface::class,
            'shopware.cart.validator' => CartValidatorInterface::class,
            'shopware.checkout.gateway.command' => AbstractCheckoutGatewayCommandHandler::class,
            'shopware.cms.data_resolver' => CmsElementResolverInterface::class,
            'shopware.cms.product_slider.processor' => AbstractProductSliderProcessor::class,
            'shopware.dal.exception_handler' => ExceptionHandlerInterface::class,
            'shopware.demodata_generator' => DemodataGeneratorInterface::class,
            'shopware.document_v2.provider' => AbstractDocumentDataProvider::class,
            'shopware.document_v2.renderer' => Shopwell\Core\Checkout\DocumentV2\Renderer\AbstractDocumentRenderer::class,
            'shopware.document_v2.type' => AbstractDocumentType::class,
            'shopware.elastic.admin-searcher-index' => AbstractAdminIndexer::class,
            'shopware.entity.definition' => EntityDefinition::class,
            'shopware.entity.hookable' => [EntityDefinition::class, Entity::class],
            'shopware.entity.seo_url.route' => EntitySeoUrlRouteInterface::class,
            'shopware.entity_indexer' => EntityIndexer::class,
            'shopware.es.definition' => AbstractElasticsearchDefinition::class,
            'shopware.filesystem.factory' => AdapterFactoryInterface::class,
            'shopware.import_export.entity_serializer' => AbstractEntitySerializer::class,
            'shopware.import_export.field_serializer' => AbstractFieldSerializer::class,
            'shopware.import_export.reader_factory' => AbstractReaderFactory::class,
            'shopware.import_export.writer_factory' => AbstractWriterFactory::class,
            'shopware.increment.gateway' => AbstractIncrementer::class,
            'shopware.legacy_encoder' => LegacyEncoderInterface::class,
            'shopware.listing.filter.handler' => AbstractListingFilterHandler::class,
            'shopware.listing.processor' => AbstractListingProcessor::class,
            'shopware.mail.data_provider' => MailFlowDataProviderInterface::class,
            'shopware.media.file_content.validator' => AbstractFileContentValidator::class,
            'shopware.media_type.detector' => TypeDetectorInterface::class,
            'shopware.metadata.loader' => MetadataLoaderInterface::class,
            'shopware.metric_transport_factory' => MetricTransportInterface::class,
            'shopware.oauth.scope' => ScopeEntityInterface::class,
            'shopware.path.strategy' => AbstractMediaPathStrategy::class,
            'shopware.payment.method' => AbstractPaymentHandler::class,
            'shopware.product.stock_filter' => AbstractStockUpdateFilter::class,
            'shopware.product_export.provider' => AbstractAgenticCommerceProductExportProvider::class,
            'shopware.product_export.validator' => ValidatorInterface::class,
            'shopware.route_scope' => AbstractRouteScope::class,
            'shopware.route_scope_whitelist' => RouteScopeWhitelistInterface::class,
            'shopware.rule.definition' => Rule::class,
            'shopware.scheduled.task' => ScheduledTask::class,
            'shopware.seo_url.route' => SeoUrlRouteInterface::class,
            'shopware.sitemap.config_handler' => ConfigHandlerInterface::class,
            'shopware.sitemap_url_provider' => AbstractUrlProvider::class,
            'shopware.snippet.filter' => SnippetFilterInterface::class,
            'shopware.storefront.captcha' => AbstractCaptcha::class,
            'shopware.sync.fk_resolver' => AbstractFkResolver::class,
            'shopware.system_check' => BaseCheck::class,
            'shopware.tax.provider' => AbstractTaxProvider::class,
            'shopware.telemetry.periodic_metric_collector' => PeriodicMetricCollectorInterface::class,
            'shopware.twig.hierarchy_builder' => TemplateNamespaceHierarchyBuilderInterface::class,
            'shopware.value_generator_connector' => AbstractIncrementStorage::class,
            'shopware.value_generator_pattern' => AbstractValueGenerator::class,
            'storefront.media.upload.validator' => StorefrontMediaValidatorInterface::class,
            'tax.rule_type_filter' => TaxRuleTypeFilterInterface::class,
        ],
    ],
];
