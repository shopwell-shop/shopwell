<?php declare(strict_types=1);

namespace Shopwell\Administration\DependencyInjection;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Shopwell\Administration\Command\CheckExtensionsCommand;
use Shopwell\Administration\Command\DeleteAdminFilesAfterBuildCommand;
use Shopwell\Administration\Command\DeleteExtensionLocalPublicFilesCommand;
use Shopwell\Administration\Command\GenerateEntitySchemaTypesCommand;
use Shopwell\Administration\Command\SetupExtensionToolingCommand;
use Shopwell\Administration\Controller\AdminExtensionApiController;
use Shopwell\Administration\Controller\AdministrationController;
use Shopwell\Administration\Controller\AdminProductStreamController;
use Shopwell\Administration\Controller\AdminSearchController;
use Shopwell\Administration\Controller\AdminTagController;
use Shopwell\Administration\Controller\DashboardController;
use Shopwell\Administration\Controller\UserConfigController;
use Shopwell\Administration\Dashboard\OrderAmountService;
use Shopwell\Administration\Framework\Adapter\Cache\Http\AdministrationCacheControlListener;
use Shopwell\Administration\Framework\Routing\KnownIps\KnownIpsCollector;
use Shopwell\Administration\Service\AdminSearcher;
use Shopwell\Administration\Snippet\AppAdministrationSnippetDefinition;
use Shopwell\Administration\Snippet\AppAdministrationSnippetPersister;
use Shopwell\Administration\Snippet\AppLifecycleSubscriber;
use Shopwell\Administration\Snippet\CachedSnippetFinder;
use Shopwell\Administration\Snippet\SnippetFinder;
use Shopwell\Administration\System\SalesChannel\Subscriber\SalesChannelUserConfigSubscriber;
use Shopwell\Core\Checkout\Cart\Price\CashRounding;
use Shopwell\Core\Checkout\Customer\Validation\CustomerEmailUniqueChecker;
use Shopwell\Core\Content\Product\ProductDefinition;
use Shopwell\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopwell\Core\Framework\Adapter\Cache\Http\Event\BeforeCacheControlEvent;
use Shopwell\Core\Framework\Adapter\Twig\TemplateFinder;
use Shopwell\Core\Framework\Api\Acl\AclCriteriaValidator;
use Shopwell\Core\Framework\Api\OAuth\SymfonyBearerTokenValidator;
use Shopwell\Core\Framework\Api\Serializer\JsonEntityEncoder;
use Shopwell\Core\Framework\App\ActionButton\Executor;
use Shopwell\Core\Framework\App\Hmac\QuerySigner;
use Shopwell\Core\Framework\App\Payload\AppPayloadServiceHelper;
use Shopwell\Core\Framework\App\Source\SourceResolver;
use Shopwell\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopwell\Core\Framework\Notification\Api\NotificationController;
use Shopwell\Core\Framework\Notification\NotificationDefinition;
use Shopwell\Core\Framework\Store\Services\FirstRunWizardService;
use Shopwell\Core\Framework\Util\HtmlSanitizer;
use Shopwell\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopwell\Core\System\Snippet\Service\TranslationLoader;
use Shopwell\Core\System\Snippet\Struct\TranslationConfig;
use Shopwell\Core\System\Tag\Service\FilterTagIdsService;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Filesystem\Filesystem;

use function Symfony\Component\DependencyInjection\Loader\Configurator\env;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->parameters()
        ->set('env(SHOPWELL_ADMINISTRATION_PATH_NAME)', 'admin')
        ->set('shopwell_administration.path_name', env('SHOPWELL_ADMINISTRATION_PATH_NAME')->resolve());

    $services = $containerConfigurator->services();

    $services->set(DeleteAdminFilesAfterBuildCommand::class)
        ->args([
            service(Filesystem::class),
        ])
        ->tag('console.command');

    $services->set(DeleteExtensionLocalPublicFilesCommand::class)
        ->args([
            service('kernel'),
        ])
        ->tag('console.command');

    $services->set(CheckExtensionsCommand::class)
        ->args([
            service('kernel'),
        ])
        ->tag('console.command');

    $services->set(SetupExtensionToolingCommand::class)
        ->args([
            service('kernel'),
        ])
        ->tag('console.command');

    $services->set(GenerateEntitySchemaTypesCommand::class)
        ->tag('console.command');

    $services->set(AdminExtensionApiController::class)
        ->public()
        ->args([
            service(Executor::class),
            service(AppPayloadServiceHelper::class),
            service('app.repository'),
            service(QuerySigner::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(AdministrationController::class)
        ->public()
        ->args([
            service(TemplateFinder::class),
            service(FirstRunWizardService::class),
            service(SnippetFinder::class),
            param('kernel.supported_api_versions'),
            service(KnownIpsCollector::class),
            service(Connection::class),
            service('event_dispatcher'),
            param('kernel.shopwell_core_dir'),
            service('customer.repository'),
            service('currency.repository'),
            service(HtmlSanitizer::class),
            service(DefinitionInstanceRegistry::class),
            service('parameter_bag'),
            service('shopwell.filesystem.asset'),
            param('shopwell.service_registry.url'),
            service('language.repository'),
            service(SymfonyBearerTokenValidator::class),
            env('PRODUCT_ANALYTICS_GATEWAY_URL'),
            service(CustomerEmailUniqueChecker::class),
            param('shopwell.api.refresh_token_ttl'),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(AdminSearchController::class)
        ->public()
        ->args([
            service(RequestCriteriaBuilder::class),
            service(DefinitionInstanceRegistry::class),
            service(AdminSearcher::class),
            service('serializer'),
            service(AclCriteriaValidator::class),
            service(DefinitionInstanceRegistry::class),
            service(JsonEntityEncoder::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(UserConfigController::class)
        ->public()
        ->args([
            service('user_config.repository'),
            service(Connection::class),
            service(ClockInterface::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(AdminProductStreamController::class)
        ->public()
        ->args([
            service(ProductDefinition::class),
            service('sales_channel.product.repository'),
            service(SalesChannelContextService::class),
            service(RequestCriteriaBuilder::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(AdminTagController::class)
        ->public()
        ->args([
            service(FilterTagIdsService::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->alias(
        'Shopwell\Administration\Controller\NotificationController',
        NotificationController::class,
    )
        ->public()
        ->deprecate('shopwell/administration', '6.7.15.0', 'The "%alias_id%" service alias is deprecated and will be removed in v6.8.0. Use Shopwell\Core\Framework\Notification\Api\NotificationController instead.');

    $services->set(AdminSearcher::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
        ]);

    $services->set(AppAdministrationSnippetDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(AppAdministrationSnippetPersister::class)
        ->args([
            service('app_administration_snippet.repository'),
            service('locale.repository'),
            service(CacheInvalidator::class),
            service(Filesystem::class),
        ]);

    $services->set(SnippetFinder::class)
        ->args([
            service('kernel'),
            service(Connection::class),
            service('shopwell.filesystem.translation'),
            service(TranslationConfig::class),
            service(TranslationLoader::class),
            service(HtmlSanitizer::class),
            service('logger'),
            param('kernel.debug'),
        ]);

    $services->set(CachedSnippetFinder::class)
        ->decorate(SnippetFinder::class)
        ->args([
            service(CachedSnippetFinder::class . '.inner'),
            service('cache.object'),
        ]);

    $services->set(AppLifecycleSubscriber::class)
        ->args([
            service(SourceResolver::class),
            service(AppAdministrationSnippetPersister::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->alias(
        'Shopwell\Administration\Notification\NotificationDefinition',
        NotificationDefinition::class,
    )->deprecate('shopwell/administration', '6.7.15.0', 'The "%alias_id%" service alias is deprecated and will be removed in v6.8.0. Use Shopwell\Core\Framework\Notification\NotificationDefinition instead.');

    $services->set(SalesChannelUserConfigSubscriber::class)
        ->args([
            service('user_config.repository'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(OrderAmountService::class)
        ->args([
            service(Connection::class),
            service(CashRounding::class),
            param('shopwell.dbal.time_zone_support_enabled'),
        ]);

    $services->set(DashboardController::class)
        ->public()
        ->args([
            service(OrderAmountService::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(AdministrationCacheControlListener::class)
        ->tag('kernel.event_listener', ['event' => BeforeCacheControlEvent::class]);
};
