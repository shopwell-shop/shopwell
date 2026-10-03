<?php declare(strict_types=1);

namespace Shopwell\Core\System\DependencyInjection;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Shopwell\Core\Checkout\Cart\CartCalculator;
use Shopwell\Core\Checkout\Cart\CartPersister;
use Shopwell\Core\Checkout\Cart\CartRuleLoader;
use Shopwell\Core\Checkout\Cart\Order\OrderConverter;
use Shopwell\Core\Checkout\Cart\SalesChannel\CartService;
use Shopwell\Core\Checkout\Cart\Tax\TaxDetector;
use Shopwell\Core\Checkout\Customer\SalesChannel\AccountService;
use Shopwell\Core\Checkout\Customer\SalesChannel\RegisterRoute;
use Shopwell\Core\Content\Media\MediaUrlPlaceholderHandlerInterface;
use Shopwell\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopwell\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopwell\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopwell\Core\Framework\Adapter\Twig\NamespaceHierarchy\NamespaceHierarchyBuilder;
use Shopwell\Core\Framework\Adapter\Twig\TemplateFinder;
use Shopwell\Core\Framework\Api\ApiDefinition\DefinitionService;
use Shopwell\Core\Framework\Api\Route\ApiRouteInfoResolver;
use Shopwell\Core\Framework\App\Context\Gateway\AppContextGateway;
use Shopwell\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopwell\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopwell\Core\Framework\DataAbstractionLayer\Indexing\ManyToManyIdFieldUpdater;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Gateway\Context\Command\Executor\ContextGatewayCommandExecutor;
use Shopwell\Core\Framework\Gateway\Context\Command\Executor\ContextGatewayCommandValidator;
use Shopwell\Core\Framework\Gateway\Context\Command\Handler\AddCustomerMessageCommandHandler;
use Shopwell\Core\Framework\Gateway\Context\Command\Handler\ChangeAddressCommandHandler;
use Shopwell\Core\Framework\Gateway\Context\Command\Handler\ChangeCheckoutOptionsCommandHandler;
use Shopwell\Core\Framework\Gateway\Context\Command\Handler\ChangeCurrencyCommandHandler;
use Shopwell\Core\Framework\Gateway\Context\Command\Handler\ChangeLanguageCommandHandler;
use Shopwell\Core\Framework\Gateway\Context\Command\Handler\ChangeShippingLocationCommandHandler;
use Shopwell\Core\Framework\Gateway\Context\Command\Handler\LoginCustomerCommandHandler;
use Shopwell\Core\Framework\Gateway\Context\Command\Handler\RegisterCustomerCommandHandler;
use Shopwell\Core\Framework\Gateway\Context\Command\Registry\ContextGatewayCommandRegistry;
use Shopwell\Core\Framework\Gateway\Context\SalesChannel\ContextGatewayRoute;
use Shopwell\Core\Framework\Log\ExceptionLogger;
use Shopwell\Core\Framework\Validation\DataValidator;
use Shopwell\Core\System\SalesChannel\Aggregate\SalesChannelAnalytics\SalesChannelAnalyticsDefinition;
use Shopwell\Core\System\SalesChannel\Aggregate\SalesChannelCountry\SalesChannelCountryDefinition;
use Shopwell\Core\System\SalesChannel\Aggregate\SalesChannelCurrency\SalesChannelCurrencyDefinition;
use Shopwell\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainDefinition;
use Shopwell\Core\System\SalesChannel\Aggregate\SalesChannelFile\SalesChannelFileDefinition;
use Shopwell\Core\System\SalesChannel\Aggregate\SalesChannelLanguage\SalesChannelLanguageDefinition;
use Shopwell\Core\System\SalesChannel\Aggregate\SalesChannelPaymentMethod\SalesChannelPaymentMethodDefinition;
use Shopwell\Core\System\SalesChannel\Aggregate\SalesChannelShippingMethod\SalesChannelShippingMethodDefinition;
use Shopwell\Core\System\SalesChannel\Aggregate\SalesChannelTranslation\SalesChannelTranslationDefinition;
use Shopwell\Core\System\SalesChannel\Aggregate\SalesChannelType\SalesChannelTypeDefinition;
use Shopwell\Core\System\SalesChannel\Aggregate\SalesChannelTypeTranslation\SalesChannelTypeTranslationDefinition;
use Shopwell\Core\System\SalesChannel\Api\StoreApiResponseListener;
use Shopwell\Core\System\SalesChannel\Api\StructEncoder;
use Shopwell\Core\System\SalesChannel\Capability\SalesChannelTypeCapabilityRegistry;
use Shopwell\Core\System\SalesChannel\Context\BaseSalesChannelContextFactory;
use Shopwell\Core\System\SalesChannel\Context\CachedBaseSalesChannelContextFactory;
use Shopwell\Core\System\SalesChannel\Context\CachedSalesChannelContextFactory;
use Shopwell\Core\System\SalesChannel\Context\CartRestorer;
use Shopwell\Core\System\SalesChannel\Context\Cleanup\CleanupSalesChannelContextTask;
use Shopwell\Core\System\SalesChannel\Context\Cleanup\CleanupSalesChannelContextTaskHandler;
use Shopwell\Core\System\SalesChannel\Context\ContextFactory;
use Shopwell\Core\System\SalesChannel\Context\InvalidationRaceAwareCache;
use Shopwell\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopwell\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopwell\Core\System\SalesChannel\Context\SalesChannelContextRequestRestorer;
use Shopwell\Core\System\SalesChannel\Context\SalesChannelContextRestorer;
use Shopwell\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopwell\Core\System\SalesChannel\Context\SalesChannelContextValueResolver;
use Shopwell\Core\System\SalesChannel\Cookie\AnalyticsCookieCollectListener;
use Shopwell\Core\System\SalesChannel\DataAbstractionLayer\SalesChannelIndexer;
use Shopwell\Core\System\SalesChannel\Entity\DefinitionRegistryChain;
use Shopwell\Core\System\SalesChannel\Entity\SalesChannelDefinitionInstanceRegistry;
use Shopwell\Core\System\SalesChannel\File\Api\SalesChannelFileAdministrationReader;
use Shopwell\Core\System\SalesChannel\File\Api\SalesChannelFileController;
use Shopwell\Core\System\SalesChannel\File\Discovery\SalesChannelFileDiscovery;
use Shopwell\Core\System\SalesChannel\File\Loader\SalesChannelFileConfigurationLoader;
use Shopwell\Core\System\SalesChannel\File\Loader\SalesChannelFileLoader;
use Shopwell\Core\System\SalesChannel\File\Rendering\SalesChannelFileRenderer;
use Shopwell\Core\System\SalesChannel\File\Rendering\SalesChannelFileStoreApiMcpSubscriber;
use Shopwell\Core\System\SalesChannel\File\Rendering\SalesChannelFileTemplateOverrideLoader;
use Shopwell\Core\System\SalesChannel\File\SalesChannelFileCacheInvalidator;
use Shopwell\Core\System\SalesChannel\File\SalesChannelFileNotFoundSubscriber;
use Shopwell\Core\System\SalesChannel\File\SalesChannelFileRequestPathResolver;
use Shopwell\Core\System\SalesChannel\File\SalesChannelFileTemplateResolver;
use Shopwell\Core\System\SalesChannel\SalesChannel\ContextRoute;
use Shopwell\Core\System\SalesChannel\SalesChannel\ContextSwitchRoute;
use Shopwell\Core\System\SalesChannel\SalesChannel\SalesChannelContextSwitcher;
use Shopwell\Core\System\SalesChannel\SalesChannel\StoreApiInfoController;
use Shopwell\Core\System\SalesChannel\SalesChannelDefinition;
use Shopwell\Core\System\SalesChannel\SalesChannelExceptionHandler;
use Shopwell\Core\System\SalesChannel\StoreApiCustomFieldMapper;
use Shopwell\Core\System\SalesChannel\Subscriber\SalesChannelMaintenanceIpAllowlistSyncSubscriber;
use Shopwell\Core\System\SalesChannel\Subscriber\SalesChannelTypeValidator;
use Shopwell\Core\System\SalesChannel\Telemetry\SalesChannelTypeResolver;
use Shopwell\Core\System\SalesChannel\Validation\SalesChannelValidator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpFoundation\RequestStack;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(SalesChannelDefinition::class)
        ->tag('shopwell.entity.definition')
        ->tag('shopwell.entity.hookable');

    $services->set(SalesChannelTranslationDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(SalesChannelCountryDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(SalesChannelCurrencyDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(SalesChannelDomainDefinition::class)
        ->tag('shopwell.entity.definition')
        ->tag('shopwell.entity.hookable');

    $services->set(SalesChannelLanguageDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(SalesChannelPaymentMethodDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(SalesChannelShippingMethodDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(SalesChannelTypeDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(SalesChannelTypeTranslationDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(SalesChannelAnalyticsDefinition::class)
        ->tag('shopwell.entity.definition', ['entity' => 'sales_channel_analytics']);

    $services->set(SalesChannelFileDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(SalesChannelFileTemplateOverrideLoader::class)
        ->tag('twig.loader', ['priority' => 100])
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(SalesChannelFileDiscovery::class)
        ->public()
        ->args([
            service('twig.template_iterator'),
            service('cache.object'),
        ]);

    $services->set(SalesChannelFileConfigurationLoader::class)
        ->args([
            service('sales_channel_file.repository'),
        ]);

    $services->set(SalesChannelFileTemplateResolver::class)
        ->args([
            service(TemplateFinder::class),
            service(NamespaceHierarchyBuilder::class),
            service('twig.loader'),
            service('event_dispatcher'),
        ]);

    $services->set(SalesChannelFileAdministrationReader::class)
        ->args([
            service(SalesChannelFileDiscovery::class),
            service(SalesChannelFileConfigurationLoader::class),
            service('twig'),
            service(SalesChannelFileTemplateResolver::class),
        ]);

    $services->set(SalesChannelFileRequestPathResolver::class);

    $services->set(SalesChannelFileRenderer::class)
        ->args([
            service('twig'),
            service(SalesChannelFileTemplateResolver::class),
            service(SalesChannelFileTemplateOverrideLoader::class),
            service(SeoUrlPlaceholderHandlerInterface::class),
            service('sales_channel.repository'),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(SalesChannelFileStoreApiMcpSubscriber::class)
        ->args([
            service('router'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(SalesChannelFileLoader::class)
        ->public()
        ->args([
            service(SalesChannelFileDiscovery::class),
            service(SalesChannelFileConfigurationLoader::class),
            service(SalesChannelFileRenderer::class),
            service(CacheTagCollector::class),
        ]);

    $services->set(SalesChannelFileCacheInvalidator::class)
        ->args([
            service(CacheInvalidator::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(SalesChannelFileNotFoundSubscriber::class)
        ->args([
            service(SalesChannelFileLoader::class),
            service(SalesChannelFileRequestPathResolver::class),
            service(SalesChannelContextRequestRestorer::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(SalesChannelFileController::class)
        ->public()
        ->args([
            service(SalesChannelFileAdministrationReader::class),
            service(SalesChannelFileLoader::class),
            service(SalesChannelContextFactory::class),
            service(SalesChannelFileRequestPathResolver::class),
        ])
        ->call('setContainer', [
            service('service_container'),
        ]);

    $services->set(SalesChannelContextPersister::class)
        ->args([
            service(Connection::class),
            service('event_dispatcher'),
            service(CartPersister::class),
            service(ClockInterface::class),
            param('shopwell.api.store.context_lifetime'),
        ]);

    $services->set(SalesChannelContextRequestRestorer::class)
        ->args([
            service(SalesChannelContextService::class),
        ]);

    $services->set(SalesChannelContextFactory::class)
        ->public()
        ->args([
            service('customer.repository'),
            service('customer_group.repository'),
            service('customer_address.repository'),
            service('payment_method.repository'),
            service(TaxDetector::class),
            tagged_iterator('tax.rule_type_filter'),
            service('event_dispatcher'),
            service('currency_country_rounding.repository'),
            service(BaseSalesChannelContextFactory::class),
        ]);

    $services->set(BaseSalesChannelContextFactory::class)
        ->args([
            service('sales_channel.repository'),
            service('customer_group.repository'),
            service('country.repository'),
            service('tax.repository'),
            service('payment_method.repository'),
            service('shipping_method.repository'),
            service('country_state.repository'),
            service('currency_country_rounding.repository'),
            service(ContextFactory::class),
            service('language.repository'),
        ]);

    $services->set(ContextFactory::class)
        ->args([
            service(Connection::class),
            service('event_dispatcher'),
        ]);

    $services->set(CachedBaseSalesChannelContextFactory::class)
        ->decorate(BaseSalesChannelContextFactory::class)
        ->args([
            service(CachedBaseSalesChannelContextFactory::class . '.inner'),
            service(InvalidationRaceAwareCache::class),
        ]);

    $services->set(InvalidationRaceAwareCache::class)
        ->args([
            service('cache.object'),
        ]);

    $services->set(CachedSalesChannelContextFactory::class)
        ->decorate(SalesChannelContextFactory::class, null, -1000)
        ->public()
        ->args([
            service(CachedSalesChannelContextFactory::class . '.inner'),
            service(InvalidationRaceAwareCache::class),
        ]);

    $services->set(SalesChannelContextService::class)
        ->args([
            service(SalesChannelContextFactory::class),
            service(CartCalculator::class),
            service(SalesChannelContextPersister::class),
            service(CartService::class),
            service('event_dispatcher'),
            service(RequestStack::class),
        ]);

    $services->set(SalesChannelContextRestorer::class)
        ->args([
            service(SalesChannelContextFactory::class),
            service(CartRuleLoader::class),
            service(OrderConverter::class),
            service('order.repository'),
            service(Connection::class),
            service('event_dispatcher'),
        ]);

    $services->set(CartRestorer::class)
        ->args([
            service(SalesChannelContextFactory::class),
            service(SalesChannelContextPersister::class),
            service(CartService::class),
            service(CartCalculator::class),
            service(CartPersister::class),
            service('event_dispatcher'),
            service(RequestStack::class),
        ]);

    $services->set(StoreApiInfoController::class)
        ->public()
        ->args([
            service(DefinitionService::class),
            service('twig'),
            param('shopwell.security.csp_templates'),
            service(ApiRouteInfoResolver::class),
        ]);

    $services->set(SalesChannelContextSwitcher::class)
        ->args([
            service(ContextSwitchRoute::class),
        ])
        ->tag('shopwell.inactiveFeature', ['flag' => 'v6.8.0.0']);

    $services->set(ContextSwitchRoute::class)
        ->public()
        ->args([
            service(DataValidator::class),
            service(SalesChannelContextPersister::class),
            service('event_dispatcher'),
            service(SalesChannelContextService::class),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(ContextRoute::class)
        ->public()
        ->args([
            service(ExtensionDispatcher::class),
        ]);

    $services->set(SalesChannelDefinitionInstanceRegistry::class)
        ->public()
        ->args([
            '',
            service('service_container'),
            [],
            [],
        ]);

    $services->set(DefinitionRegistryChain::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
            service(SalesChannelDefinitionInstanceRegistry::class),
        ]);

    $services->set(SalesChannelContextValueResolver::class)
        ->tag('controller.argument_value_resolver', ['priority' => 1000]);

    $services->set(SalesChannelExceptionHandler::class)
        ->tag('shopwell.dal.exception_handler');

    $services->set(StoreApiResponseListener::class)
        ->tag('kernel.event_subscriber')
        ->args([
            service(StructEncoder::class),
            service('event_dispatcher'),
            service(SeoUrlPlaceholderHandlerInterface::class),
            service(MediaUrlPlaceholderHandlerInterface::class),
        ]);

    $services->set(StructEncoder::class)
        ->args([
            service(DefinitionRegistryChain::class),
            service('serializer'),
            service(Connection::class),
        ])
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(SalesChannelIndexer::class)
        ->args([
            service(IteratorFactory::class),
            service('sales_channel.repository'),
            service('event_dispatcher'),
            service(ManyToManyIdFieldUpdater::class),
        ])
        ->tag('shopwell.entity_indexer');

    $services->set(CleanupSalesChannelContextTask::class)
        ->tag('shopwell.scheduled.task');

    $services->set(CleanupSalesChannelContextTaskHandler::class)
        ->args([
            service('scheduled_task.repository'),
            service('logger'),
            service(Connection::class),
            param('shopwell.sales_channel_context.expire_days'),
            service(ClockInterface::class),
        ])
        ->tag('messenger.message_handler');

    $services->set(SalesChannelValidator::class)
        ->args([
            service(Connection::class),
            service(SalesChannelTypeCapabilityRegistry::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(SalesChannelTypeCapabilityRegistry::class)
        ->args([
            tagged_iterator('shopwell.sales_channel.type_capabilities'),
        ]);

    $services->set(SalesChannelTypeValidator::class)
        ->tag('kernel.event_subscriber');

    $services->set(AnalyticsCookieCollectListener::class)
        ->args([
            service('sales_channel_analytics.repository'),
        ])
        ->tag('kernel.event_listener');

    $services->set(StoreApiCustomFieldMapper::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('kernel.reset', ['method' => 'reset']);

    // Context Gateway
    $services->set(ContextGatewayRoute::class)
        ->public()
        ->args([
            service(AppContextGateway::class),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(ContextGatewayCommandValidator::class)
        ->args([
            service(ExceptionLogger::class),
        ]);

    $services->set(ContextGatewayCommandExecutor::class)
        ->args([
            service(ContextSwitchRoute::class),
            service(ContextGatewayCommandRegistry::class),
            service(ContextGatewayCommandValidator::class),
            service(ExceptionLogger::class),
            service(SalesChannelContextService::class),
        ]);

    $services->set(ContextGatewayCommandRegistry::class)
        ->args([
            tagged_iterator('shopwell.context.gateway.command'),
        ]);

    $services->set(AddCustomerMessageCommandHandler::class)
        ->tag('shopwell.context.gateway.command');

    $services->set(ChangeAddressCommandHandler::class)
        ->tag('shopwell.context.gateway.command');

    $services->set(ChangeCheckoutOptionsCommandHandler::class)
        ->args([
            service('payment_method.repository'),
            service('shipping_method.repository'),
        ])
        ->tag('shopwell.context.gateway.command');

    $services->set(ChangeCurrencyCommandHandler::class)
        ->args([
            service('currency.repository'),
        ])
        ->tag('shopwell.context.gateway.command');

    $services->set(ChangeLanguageCommandHandler::class)
        ->args([
            service('language.repository'),
        ])
        ->tag('shopwell.context.gateway.command');

    $services->set(ChangeShippingLocationCommandHandler::class)
        ->args([
            service('country.repository'),
            service('country_state.repository'),
        ])
        ->tag('shopwell.context.gateway.command');

    $services->set(LoginCustomerCommandHandler::class)
        ->args([
            service(AccountService::class),
        ])
        ->tag('shopwell.context.gateway.command');

    $services->set(RegisterCustomerCommandHandler::class)
        ->args([
            service(RegisterRoute::class),
        ])
        ->tag('shopwell.context.gateway.command');

    $services->set(SalesChannelMaintenanceIpAllowlistSyncSubscriber::class)
        ->tag('kernel.event_subscriber')
        ->tag('shopwell.inactiveFeature', ['flag' => 'v6.8.0.0']);

    // Telemetry: shared sales_channel_type label resolver (cart calculation, order placed metrics)
    $services->set(SalesChannelTypeResolver::class);
};
