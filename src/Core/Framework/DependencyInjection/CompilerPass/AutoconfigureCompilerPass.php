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
                $definition->addTag('shopware.entity');
            });

        $container
            ->registerForAutoconfiguration(EntityDefinition::class)
            ->addTag('shopware.entity.definition');

        $container
            ->registerForAutoconfiguration(HookableEntityInterface::class)
            ->addTag('shopware.entity.hookable');

        $container
            ->registerForAutoconfiguration(SalesChannelDefinition::class)
            ->addTag('shopware.sales_channel.entity.definition');

        $container
            ->registerForAutoconfiguration(AbstractRouteScope::class)
            ->addTag('shopware.route_scope');

        $container
            ->registerForAutoconfiguration(EntityExtension::class)
            ->addTag('shopware.entity.extension');

        $container
            ->registerForAutoconfiguration(BulkEntityExtension::class)
            ->addTag('shopware.bulk.entity.extension');

        $container
            ->registerForAutoconfiguration(CartProcessorInterface::class)
            ->addTag('shopware.cart.processor');

        $container
            ->registerForAutoconfiguration(CartDataCollectorInterface::class)
            ->addTag('shopware.cart.collector');

        $container
            ->registerForAutoconfiguration(ScheduledTask::class)
            ->addTag('shopware.scheduled.task');

        $container
            ->registerForAutoconfiguration(PeriodicMetricCollectorInterface::class)
            ->addTag('shopware.telemetry.periodic_metric_collector');

        $container
            ->registerForAutoconfiguration(CartValidatorInterface::class)
            ->addTag('shopware.cart.validator');

        $container
            ->registerForAutoconfiguration(LineItemFactoryInterface::class)
            ->addTag('shopware.cart.line_item.factory');

        $container
            ->registerForAutoconfiguration(LineItemGroupPackagerInterface::class)
            ->addTag('lineitem.group.packager');

        $container
            ->registerForAutoconfiguration(LineItemGroupSorterInterface::class)
            ->addTag('lineitem.group.sorter');

        $container
            ->registerForAutoconfiguration(LegacyEncoderInterface::class)
            ->addTag('shopware.legacy_encoder');

        $container
            ->registerForAutoconfiguration(EntityIndexer::class)
            ->addTag('shopware.entity_indexer');

        $container
            ->registerForAutoconfiguration(ExceptionHandlerInterface::class)
            ->addTag('shopware.dal.exception_handler');

        $container
            ->registerForAutoconfiguration(AbstractDocumentRenderer::class)
            ->addTag('document.renderer');

        $container
            ->registerForAutoconfiguration(AbstractPaymentHandler::class)
            ->addTag('shopware.payment.method');

        $container
            ->registerForAutoconfiguration(FilterSorterInterface::class)
            ->addTag('promotion.filter.sorter');

        $container
            ->registerForAutoconfiguration(FilterPickerInterface::class)
            ->addTag('promotion.filter.picker');

        $container
            ->registerForAutoconfiguration(Rule::class)
            ->addTag('shopware.rule.definition');

        $container
            ->registerForAutoconfiguration(AbstractTaxProvider::class)
            ->addTag('shopware.tax.provider');

        $container
            ->registerForAutoconfiguration(CmsElementResolverInterface::class)
            ->addTag('shopware.cms.data_resolver');

        $container
            ->registerForAutoconfiguration(FieldSerializerInterface::class)
            ->addTag('shopware.field_serializer');

        $container
            ->registerForAutoconfiguration(FlowStorer::class)
            ->addTag('flow.storer');

        $container
            ->registerForAutoconfiguration(AbstractUrlProvider::class)
            ->addTag('shopware.sitemap_url_provider');

        $container
            ->registerForAutoconfiguration(AdapterFactoryInterface::class)
            ->addTag('shopware.filesystem.factory');

        $container
            ->registerForAutoconfiguration(AbstractValueGenerator::class)
            ->addTag('shopware.value_generator_pattern');

        $container
            ->registerForAutoconfiguration(TaxRuleTypeFilterInterface::class)
            ->addTag('tax.rule_type_filter');

        $container
            ->registerForAutoconfiguration(SeoUrlRouteInterface::class)
            ->addTag('shopware.seo_url.route');

        $container
            ->registerForAutoconfiguration(TemplateNamespaceHierarchyBuilderInterface::class)
            ->addTag('shopware.twig.hierarchy_builder');

        $container
            ->registerForAutoconfiguration(AbstractListingProcessor::class)
            ->addTag('shopware.listing.processor');

        $container
            ->registerForAutoconfiguration(AbstractListingFilterHandler::class)
            ->addTag('shopware.listing.filter.handler');

        $container
            ->registerForAutoconfiguration(CorsHeaderProviderInterface::class)
            ->addTag(CorsHeaderProviderInterface::SERVICE_TAG);

        $container->registerAliasForArgument('shopware.filesystem.private', FilesystemOperator::class, 'privateFilesystem');
        $container->registerAliasForArgument('shopware.filesystem.public', FilesystemOperator::class, 'publicFilesystem');
    }
}
