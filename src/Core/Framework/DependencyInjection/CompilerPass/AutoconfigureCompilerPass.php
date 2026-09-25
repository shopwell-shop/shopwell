<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\DependencyInjection\CompilerPass;

use League\Flysystem\FilesystemOperator;
use Shopwell\Core\Checkout\Cart\CartDataCollectorInterface;
use Shopwell\Core\Checkout\Cart\CartProcessorInterface;
use Shopwell\Core\Checkout\Cart\CartValidatorInterface;
use Shopwell\Core\Checkout\Cart\LineItem\Group\LineItemGroupPackagerInterface;
use Shopwell\Core\Checkout\Cart\LineItem\Group\LineItemGroupSorterInterface;
use Shopwell\Core\Checkout\Cart\LineItemFactoryHandler\LineItemFactoryInterface;
use Shopwell\Core\Checkout\Cart\TaxProvider\AbstractTaxProvider;
use Shopwell\Core\Checkout\Customer\Password\LegacyEncoder\LegacyEncoderInterface;
use Shopwell\Core\Checkout\Document\Renderer\AbstractDocumentRenderer;
use Shopwell\Core\Checkout\Payment\Cart\PaymentHandler\AbstractPaymentHandler;
use Shopwell\Core\Checkout\Promotion\Cart\Discount\Filter\FilterPickerInterface;
use Shopwell\Core\Checkout\Promotion\Cart\Discount\Filter\FilterSorterInterface;
use Shopwell\Core\Content\Cms\DataResolver\Element\CmsElementResolverInterface;
use Shopwell\Core\Content\Flow\Dispatching\Storer\FlowStorer;
use Shopwell\Core\Content\Product\SalesChannel\Listing\Filter\AbstractListingFilterHandler;
use Shopwell\Core\Content\Product\SalesChannel\Listing\Processor\AbstractListingProcessor;
use Shopwell\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteInterface;
use Shopwell\Core\Content\Sitemap\Provider\AbstractUrlProvider;
use Shopwell\Core\Framework\Adapter\Filesystem\Adapter\AdapterFactoryInterface;
use Shopwell\Core\Framework\Adapter\Twig\NamespaceHierarchy\TemplateNamespaceHierarchyBuilderInterface;
use Shopwell\Core\Framework\Api\Cors\CorsHeaderProviderInterface;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\BulkEntityExtension;
use Shopwell\Core\Framework\DataAbstractionLayer\Dbal\ExceptionHandlerInterface;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\FieldSerializerInterface;
use Shopwell\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;
use Shopwell\Core\Framework\Routing\AbstractRouteScope;
use Shopwell\Core\Framework\Rule\Rule;
use Shopwell\Core\Framework\Telemetry\Metrics\Metric\PeriodicMetricCollectorInterface;
use Shopwell\Core\Framework\Webhook\Hookable\HookableEntityInterface;
use Shopwell\Core\System\NumberRange\ValueGenerator\Pattern\AbstractValueGenerator;
use Shopwell\Core\System\SalesChannel\SalesChannelDefinition;
use Shopwell\Core\System\Tax\TaxRuleType\TaxRuleTypeFilterInterface;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[Package('framework')]
class AutoconfigureCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $container
            ->registerAttributeForAutoconfiguration(Entity::class, static function (ChildDefinition $definition): void {
                $definition->addTag('shopwell.entity');
            });

        $container
            ->registerForAutoconfiguration(EntityDefinition::class)
            ->addTag('shopwell.entity.definition');

        $container
            ->registerForAutoconfiguration(HookableEntityInterface::class)
            ->addTag('shopwell.entity.hookable');

        $container
            ->registerForAutoconfiguration(SalesChannelDefinition::class)
            ->addTag('shopwell.sales_channel.entity.definition');

        $container
            ->registerForAutoconfiguration(AbstractRouteScope::class)
            ->addTag('shopwell.route_scope');

        $container
            ->registerForAutoconfiguration(EntityExtension::class)
            ->addTag('shopwell.entity.extension');

        $container
            ->registerForAutoconfiguration(BulkEntityExtension::class)
            ->addTag('shopwell.bulk.entity.extension');

        $container
            ->registerForAutoconfiguration(CartProcessorInterface::class)
            ->addTag('shopwell.cart.processor');

        $container
            ->registerForAutoconfiguration(CartDataCollectorInterface::class)
            ->addTag('shopwell.cart.collector');

        $container
            ->registerForAutoconfiguration(ScheduledTask::class)
            ->addTag('shopwell.scheduled.task');

        $container
            ->registerForAutoconfiguration(PeriodicMetricCollectorInterface::class)
            ->addTag('shopwell.telemetry.periodic_metric_collector');

        $container
            ->registerForAutoconfiguration(CartValidatorInterface::class)
            ->addTag('shopwell.cart.validator');

        $container
            ->registerForAutoconfiguration(LineItemFactoryInterface::class)
            ->addTag('shopwell.cart.line_item.factory');

        $container
            ->registerForAutoconfiguration(LineItemGroupPackagerInterface::class)
            ->addTag('lineitem.group.packager');

        $container
            ->registerForAutoconfiguration(LineItemGroupSorterInterface::class)
            ->addTag('lineitem.group.sorter');

        $container
            ->registerForAutoconfiguration(LegacyEncoderInterface::class)
            ->addTag('shopwell.legacy_encoder');

        $container
            ->registerForAutoconfiguration(EntityIndexer::class)
            ->addTag('shopwell.entity_indexer');

        $container
            ->registerForAutoconfiguration(ExceptionHandlerInterface::class)
            ->addTag('shopwell.dal.exception_handler');

        $container
            ->registerForAutoconfiguration(AbstractDocumentRenderer::class)
            ->addTag('document.renderer');

        $container
            ->registerForAutoconfiguration(AbstractPaymentHandler::class)
            ->addTag('shopwell.payment.method');

        $container
            ->registerForAutoconfiguration(FilterSorterInterface::class)
            ->addTag('promotion.filter.sorter');

        $container
            ->registerForAutoconfiguration(FilterPickerInterface::class)
            ->addTag('promotion.filter.picker');

        $container
            ->registerForAutoconfiguration(Rule::class)
            ->addTag('shopwell.rule.definition');

        $container
            ->registerForAutoconfiguration(AbstractTaxProvider::class)
            ->addTag('shopwell.tax.provider');

        $container
            ->registerForAutoconfiguration(CmsElementResolverInterface::class)
            ->addTag('shopwell.cms.data_resolver');

        $container
            ->registerForAutoconfiguration(FieldSerializerInterface::class)
            ->addTag('shopwell.field_serializer');

        $container
            ->registerForAutoconfiguration(FlowStorer::class)
            ->addTag('flow.storer');

        $container
            ->registerForAutoconfiguration(AbstractUrlProvider::class)
            ->addTag('shopwell.sitemap_url_provider');

        $container
            ->registerForAutoconfiguration(AdapterFactoryInterface::class)
            ->addTag('shopwell.filesystem.factory');

        $container
            ->registerForAutoconfiguration(AbstractValueGenerator::class)
            ->addTag('shopwell.value_generator_pattern');

        $container
            ->registerForAutoconfiguration(TaxRuleTypeFilterInterface::class)
            ->addTag('tax.rule_type_filter');

        $container
            ->registerForAutoconfiguration(SeoUrlRouteInterface::class)
            ->addTag('shopwell.seo_url.route');

        $container
            ->registerForAutoconfiguration(TemplateNamespaceHierarchyBuilderInterface::class)
            ->addTag('shopwell.twig.hierarchy_builder');

        $container
            ->registerForAutoconfiguration(AbstractListingProcessor::class)
            ->addTag('shopwell.listing.processor');

        $container
            ->registerForAutoconfiguration(AbstractListingFilterHandler::class)
            ->addTag('shopwell.listing.filter.handler');

        $container
            ->registerForAutoconfiguration(CorsHeaderProviderInterface::class)
            ->addTag(CorsHeaderProviderInterface::SERVICE_TAG);

        $container->registerAliasForArgument('shopwell.filesystem.private', FilesystemOperator::class, 'privateFilesystem');
        $container->registerAliasForArgument('shopwell.filesystem.public', FilesystemOperator::class, 'publicFilesystem');
    }
}
