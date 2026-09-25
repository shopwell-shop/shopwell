<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\DependencyInjection;

use Doctrine\DBAL\Connection;
use Lcobucci\JWT\Configuration as JWTConfiguration;
use League\OAuth2\Server\AuthorizationServer;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Clock\ClockInterface;
use Psr\Container\ContainerInterface;
use Shopwell\Core\Content\Flow\Api\FlowActionCollector;
use Shopwell\Core\Content\Media\Upload\MediaFileExtensionListProvider;
use Shopwell\Core\Content\Media\Upload\PresignedMediaUploadService;
use Shopwell\Core\Framework\Adapter\Cache\CacheClearer;
use Shopwell\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopwell\Core\Framework\Api\Acl\AclCriteriaValidator;
use Shopwell\Core\Framework\Api\ApiDefinition\DefinitionService;
use Shopwell\Core\Framework\Api\ApiDefinition\Generator\AllStoreApiSchemaMigrationScopeProvider;
use Shopwell\Core\Framework\Api\ApiDefinition\Generator\BundleSchemaPathCollection;
use Shopwell\Core\Framework\Api\ApiDefinition\Generator\CachedEntitySchemaGenerator;
use Shopwell\Core\Framework\Api\ApiDefinition\Generator\CoreStoreApiSchemaMigrationScopeProvider;
use Shopwell\Core\Framework\Api\ApiDefinition\Generator\EntitySchemaGenerator;
use Shopwell\Core\Framework\Api\ApiDefinition\Generator\OpenApi\OpenApiDefinitionSchemaBuilder;
use Shopwell\Core\Framework\Api\ApiDefinition\Generator\OpenApi\OpenApiPathBuilder;
use Shopwell\Core\Framework\Api\ApiDefinition\Generator\OpenApi\OpenApiSchemaBuilder;
use Shopwell\Core\Framework\Api\ApiDefinition\Generator\OpenApi3Generator;
use Shopwell\Core\Framework\Api\ApiDefinition\Generator\OpenApiRouteDefaultsFilter;
use Shopwell\Core\Framework\Api\ApiDefinition\Generator\StoreApiGenerator;
use Shopwell\Core\Framework\Api\ApiDefinition\Generator\StoreApiSchemaMigrationReporter;
use Shopwell\Core\Framework\Api\ApiDefinition\Generator\StoreApiSchemaMigrationScopeProviderInterface;
use Shopwell\Core\Framework\Api\Command\CreateIntegrationCommand;
use Shopwell\Core\Framework\Api\Command\DumpClassSchemaCommand;
use Shopwell\Core\Framework\Api\Command\DumpSchemaCommand;
use Shopwell\Core\Framework\Api\Command\StoreApiSchemaMigrationReportCommand;
use Shopwell\Core\Framework\Api\Context\ContextValueResolver;
use Shopwell\Core\Framework\Api\Controller\AccessKeyController;
use Shopwell\Core\Framework\Api\Controller\ApiController;
use Shopwell\Core\Framework\Api\Controller\AuthController;
use Shopwell\Core\Framework\Api\Controller\CacheController;
use Shopwell\Core\Framework\Api\Controller\CustomSnippetFormatController;
use Shopwell\Core\Framework\Api\Controller\FallbackController;
use Shopwell\Core\Framework\Api\Controller\FeatureFlagController;
use Shopwell\Core\Framework\Api\Controller\HealthCheckController;
use Shopwell\Core\Framework\Api\Controller\IndexingController;
use Shopwell\Core\Framework\Api\Controller\InfoController;
use Shopwell\Core\Framework\Api\Controller\IntegrationController;
use Shopwell\Core\Framework\Api\Controller\OAuthAuthorizeController;
use Shopwell\Core\Framework\Api\Controller\SyncController;
use Shopwell\Core\Framework\Api\Controller\UserController;
use Shopwell\Core\Framework\Api\Cors\CoreCorsHeaderProvider;
use Shopwell\Core\Framework\Api\Cors\CorsHeaderProviderInterface;
use Shopwell\Core\Framework\Api\EventListener\Authentication\ApiAuthenticationListener;
use Shopwell\Core\Framework\Api\EventListener\Authentication\SalesChannelAuthenticationListener;
use Shopwell\Core\Framework\Api\EventListener\Authentication\UserCredentialsChangedSubscriber;
use Shopwell\Core\Framework\Api\EventListener\CorsListener;
use Shopwell\Core\Framework\Api\EventListener\ExpectationSubscriber;
use Shopwell\Core\Framework\Api\EventListener\JsonRequestTransformerListener;
use Shopwell\Core\Framework\Api\EventListener\ResponseExceptionListener;
use Shopwell\Core\Framework\Api\EventListener\ResponseHeaderListener;
use Shopwell\Core\Framework\Api\OAuth\AccessTokenRepository;
use Shopwell\Core\Framework\Api\OAuth\AuthCodeRepository;
use Shopwell\Core\Framework\Api\OAuth\Client\PublicClientRegistry;
use Shopwell\Core\Framework\Api\OAuth\ClientRepository;
use Shopwell\Core\Framework\Api\OAuth\FakeCryptKey;
use Shopwell\Core\Framework\Api\OAuth\GrantTypeFactory;
use Shopwell\Core\Framework\Api\OAuth\JWTConfigurationFactory;
use Shopwell\Core\Framework\Api\OAuth\RefreshTokenRepository;
use Shopwell\Core\Framework\Api\OAuth\Scope\AdminScope;
use Shopwell\Core\Framework\Api\OAuth\Scope\UserVerifiedScope;
use Shopwell\Core\Framework\Api\OAuth\Scope\WriteScope;
use Shopwell\Core\Framework\Api\OAuth\ScopeRepository;
use Shopwell\Core\Framework\Api\OAuth\SymfonyBearerTokenValidator;
use Shopwell\Core\Framework\Api\OAuth\UserRepository;
use Shopwell\Core\Framework\Api\Response\ResponseFactoryInterfaceValueResolver;
use Shopwell\Core\Framework\Api\Response\ResponseFactoryRegistry;
use Shopwell\Core\Framework\Api\Response\Type\Api\JsonApiType;
use Shopwell\Core\Framework\Api\Response\Type\Api\JsonType;
use Shopwell\Core\Framework\Api\Route\ApiRouteInfoResolver;
use Shopwell\Core\Framework\Api\Route\ApiRouteLoader;
use Shopwell\Core\Framework\Api\Serializer\JsonApiDecoder;
use Shopwell\Core\Framework\Api\Serializer\JsonApiEncoder;
use Shopwell\Core\Framework\Api\Serializer\JsonEntityEncoder;
use Shopwell\Core\Framework\Api\Sync\SyncService;
use Shopwell\Core\Framework\App\ShopId\ShopIdProvider;
use Shopwell\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityProtection\EntityProtectionValidator;
use Shopwell\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Shopwell\Core\Framework\Event\BusinessEventCollector;
use Shopwell\Core\Framework\Feature\FeatureFlagRegistry;
use Shopwell\Core\Framework\MessageQueue\Stats\StatsService;
use Shopwell\Core\Framework\Migration\MigrationInfo;
use Shopwell\Core\Framework\Plugin\KernelPluginCollection;
use Shopwell\Core\Framework\Routing\MaintenanceModeResolver;
use Shopwell\Core\Framework\Routing\RequestTransformer;
use Shopwell\Core\Framework\Routing\RequestTransformerInterface;
use Shopwell\Core\Framework\Routing\RouteScopeRegistry;
use Shopwell\Core\Framework\Sso\Config\LoginConfigService;
use Shopwell\Core\Framework\Sso\SsoService;
use Shopwell\Core\Framework\Sso\TokenService\ExternalTokenService;
use Shopwell\Core\Framework\Sso\UserService\UserService;
use Shopwell\Core\Framework\Store\InAppPurchase;
use Shopwell\Core\Framework\SystemCheck\SystemChecker;
use Shopwell\Core\Framework\Validation\DataValidator;
use Shopwell\Core\Framework\Validation\HappyPathValidator;
use Shopwell\Core\Maintenance\System\Service\AppUrlVerifier;
use Shopwell\Core\System\SalesChannel\Api\StructEncoder;
use Shopwell\Core\System\SalesChannel\Entity\SalesChannelDefinitionInstanceRegistry;
use Shopwell\Core\System\SystemConfig\SystemConfigService;
use Shopwell\Core\System\User\UserDefinition;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\env;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(RequestTransformerInterface::class, RequestTransformer::class)
        ->public();

    $services->set(FallbackController::class)
        ->public()
        ->call('setContainer', [service('service_container')]);

    $services->set(CoreCorsHeaderProvider::class)
        ->tag(CorsHeaderProviderInterface::SERVICE_TAG, ['priority' => 1000]);

    $services->set(CorsListener::class)
        ->args([
            tagged_iterator(CorsHeaderProviderInterface::SERVICE_TAG),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(ResponseExceptionListener::class)
        ->args([
            param('kernel.debug'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(ResponseHeaderListener::class)
        ->tag('kernel.event_subscriber');

    $services->set(ContextValueResolver::class)
        ->tag('controller.argument_value_resolver', ['priority' => 1000]);

    $services->set(AccessKeyController::class)
        ->public()
        ->call('setContainer', [service('service_container')]);

    $services->set(ApiController::class)
        ->public()
        ->args([
            service(DefinitionInstanceRegistry::class),
            service('serializer'),
            service('api.request_criteria_builder'),
            service(EntityProtectionValidator::class),
            service(AclCriteriaValidator::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(SyncController::class)
        ->public()
        ->args([
            service(SyncService::class),
            service('serializer'),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(HealthCheckController::class)
        ->public()
        ->args([
            service('event_dispatcher'),
            service(SystemChecker::class),
            service(SymfonyBearerTokenValidator::class),
            param('shopwell.api.static_token.health_check'),
        ]);

    $services->set(IndexingController::class)
        ->public()
        ->args([
            service(EntityIndexerRegistry::class),
            service('messenger.default_bus'),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(DumpSchemaCommand::class)
        ->args([
            service(DefinitionService::class),
            service('cache.object'),
        ])
        ->tag('console.command');

    $services->set(DumpClassSchemaCommand::class)
        ->args([
            param('kernel.bundles_metadata'),
        ])
        ->tag('console.command');

    $services->set(CreateIntegrationCommand::class)
        ->args([
            service('integration.repository'),
        ])
        ->tag('console.command');

    $services->set(StoreApiSchemaMigrationReportCommand::class)
        ->args([
            service(StoreApiSchemaMigrationReporter::class),
            service(SalesChannelDefinitionInstanceRegistry::class),
        ])
        ->tag('console.command');

    $services->set(JsonApiDecoder::class)
        ->tag('serializer.encoder');

    $services->set(ResponseFactoryRegistry::class)
        ->args([
            service(JsonApiType::class),
            // deactivated, the current sales channel api design does not match the json api requirements
            service(JsonType::class),
        ]);

    $services->set(JsonApiType::class)
        ->args([
            service(JsonApiEncoder::class),
            service(StructEncoder::class),
        ]);

    $services->set(JsonApiEncoder::class);

    $services->set(JsonEntityEncoder::class)
        ->args([
            service('serializer'),
        ]);

    $services->set(JsonType::class)
        ->args([
            service(JsonEntityEncoder::class),
            service(StructEncoder::class),
        ]);

    $services->set(DefinitionService::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
            service(SalesChannelDefinitionInstanceRegistry::class),
            service(StoreApiGenerator::class),
            service(OpenApi3Generator::class),
            service(EntitySchemaGenerator::class),
        ]);

    $services->set(OpenApiDefinitionSchemaBuilder::class)
        ->args([
            tagged_iterator('shopwell.api.enum_provider'),
        ]);

    $services->set(OpenApiPathBuilder::class);

    $services->set(OpenApiSchemaBuilder::class)
        ->args([
            param('kernel.shopwell_version'),
        ]);

    $services->set(OpenApiRouteDefaultsFilter::class)
        ->args([
            service('router'),
        ]);

    $services->set(BundleSchemaPathCollection::class)
        ->args([
            service('kernel.bundles'),
        ]);

    $services->set(OpenApi3Generator::class)
        ->args([
            service(OpenApiSchemaBuilder::class),
            service(OpenApiPathBuilder::class),
            service(OpenApiDefinitionSchemaBuilder::class),
            param('kernel.bundles_metadata'),
            service(BundleSchemaPathCollection::class),
            service(OpenApiRouteDefaultsFilter::class),
        ]);

    $services->set(StoreApiGenerator::class)
        ->args([
            service(OpenApiSchemaBuilder::class),
            service(OpenApiDefinitionSchemaBuilder::class),
            param('kernel.bundles_metadata'),
            service(BundleSchemaPathCollection::class),
            service(OpenApiRouteDefaultsFilter::class),
        ]);

    $services->set(CoreStoreApiSchemaMigrationScopeProvider::class)
        ->tag(StoreApiSchemaMigrationScopeProviderInterface::SERVICE_TAG);

    $services->set(AllStoreApiSchemaMigrationScopeProvider::class)
        ->args([
            service(BundleSchemaPathCollection::class),
        ])
        ->tag(StoreApiSchemaMigrationScopeProviderInterface::SERVICE_TAG);

    $services->set(StoreApiSchemaMigrationReporter::class)
        ->args([
            service(OpenApiDefinitionSchemaBuilder::class),
            tagged_iterator(StoreApiSchemaMigrationScopeProviderInterface::SERVICE_TAG),
        ]);

    $services->set(EntitySchemaGenerator::class);

    $services->set(CachedEntitySchemaGenerator::class)
        ->decorate(EntitySchemaGenerator::class)
        ->args([
            service(CachedEntitySchemaGenerator::class . '.inner'),
            service('cache.object'),
        ]);

    $services->set(InfoController::class)
        ->public()
        ->args([
            service(DefinitionService::class),
            service('parameter_bag'),
            service(BusinessEventCollector::class),
            service('shopwell.increment.gateway.registry'),
            service(MigrationInfo::class),
            service(AppUrlVerifier::class),
            service(FlowActionCollector::class),
            service(SystemConfigService::class),
            service(ApiRouteInfoResolver::class),
            service(InAppPurchase::class),
            service(ShopIdProvider::class),
            service(StatsService::class),
            service('event_dispatcher'),
            service(PresignedMediaUploadService::class)->nullOnInvalid(),
            service(MediaFileExtensionListProvider::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(AuthController::class)
        ->public()
        ->args([
            service('shopwell.api.authorization_server'),
            service(PsrHttpFactory::class),
            service('shopwell.rate_limiter'),
            service(Connection::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(OAuthAuthorizeController::class)
        ->public()
        ->args([
            service('shopwell.api.authorization_server'),
            service(PsrHttpFactory::class),
            service(Psr17Factory::class),
            service(PublicClientRegistry::class),
            service('shopwell.rate_limiter'),
            service('router'),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(CacheController::class)
        ->public()
        ->args([
            service(CacheClearer::class),
            service(CacheInvalidator::class),
            service('cache.object'),
            service(EntityIndexerRegistry::class),
            service('event_dispatcher'),
        ])
        ->tag('container.service_subscriber')
        ->tag('controller.service_arguments')
        ->call('setContainer', [service(ContainerInterface::class)]);

    $services->set(AccessTokenRepository::class);

    $services->set(PublicClientRegistry::class)
        ->args([
            param('shopwell.api.oauth_clients'),
        ]);

    $services->set(ClientRepository::class)
        ->args([
            service(Connection::class),
            service(ClockInterface::class),
            service(PublicClientRegistry::class),
        ]);

    $services->set(RefreshTokenRepository::class)
        ->args([
            service(Connection::class),
            service(ClockInterface::class),
        ]);

    $services->set(ScopeRepository::class)
        ->args([
            tagged_iterator('shopwell.oauth.scope'),
            service(Connection::class),
        ]);

    $services->set(UserRepository::class)
        ->args([
            service(Connection::class),
            service(LoginConfigService::class),
        ]);

    $services->set(WriteScope::class)
        ->tag('shopwell.oauth.scope');

    $services->set(AdminScope::class)
        ->tag('shopwell.oauth.scope');

    $services->set(UserVerifiedScope::class)
        ->tag('shopwell.oauth.scope');

    $services->set('shopwell.jwt_config', JWTConfiguration::class)
        ->factory([JWTConfigurationFactory::class, 'createJWTConfiguration']);

    $services->set(FakeCryptKey::class)
        ->args([
            service('shopwell.jwt_config'),
        ]);

    $services->set('shopwell.api.authorization_server', AuthorizationServer::class)
        ->args([
            service(ClientRepository::class),
            service(AccessTokenRepository::class),
            service(ScopeRepository::class),
            service(FakeCryptKey::class),
            env('APP_SECRET'),
        ]);

    $services->set(HttpFoundationFactory::class);

    $services->set(SymfonyBearerTokenValidator::class)
        ->args([
            service(AccessTokenRepository::class),
            service(Connection::class),
            service('shopwell.jwt_config'),
        ]);

    $services->set(JsonRequestTransformerListener::class)
        ->tag('kernel.event_subscriber');

    $services->set(ExpectationSubscriber::class)
        ->args([
            param('kernel.shopwell_version'),
            param('kernel.plugin_infos'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(SalesChannelAuthenticationListener::class)
        ->args([
            service(Connection::class),
            service(RouteScopeRegistry::class),
            service(MaintenanceModeResolver::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(AuthCodeRepository::class)
        ->args([
            service(Connection::class),
            service(ClockInterface::class),
        ]);

    $services->set(GrantTypeFactory::class)
        ->args([
            service(UserRepository::class),
            service(RefreshTokenRepository::class),
            service(AuthCodeRepository::class),
            service(UserService::class),
            service(ExternalTokenService::class),
            service(ClockInterface::class),
            param('shopwell.api.refresh_token_ttl'),
            param('shopwell.api.auth_code_ttl'),
        ]);

    $services->set(ApiAuthenticationListener::class)
        ->args([
            service(SymfonyBearerTokenValidator::class),
            service('shopwell.api.authorization_server'),
            service(GrantTypeFactory::class),
            service(RouteScopeRegistry::class),
            param('shopwell.api.access_token_ttl'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(UserCredentialsChangedSubscriber::class)
        ->args([
            service(RefreshTokenRepository::class),
            service(AuthCodeRepository::class),
            service(Connection::class),
            service(ClockInterface::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(UserController::class)
        ->public()
        ->args([
            service('user.repository'),
            service('acl_user_role.repository'),
            service('acl_role.repository'),
            service('user_access_key.repository'),
            service(UserDefinition::class),
            service(SsoService::class),
            service(Connection::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(IntegrationController::class)
        ->public()
        ->args([
            service('integration.repository'),
            service(Connection::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(ResponseFactoryInterfaceValueResolver::class)
        ->args([
            service(ResponseFactoryRegistry::class),
        ])
        ->tag('controller.argument_value_resolver', ['priority' => 50]);

    $services->set(ApiRouteLoader::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('routing.loader');

    $services->set(ApiRouteInfoResolver::class)
        ->args([
            service('router.default'),
        ]);

    $services->set(DataValidator::class)
        ->args([
            service('validator'),
        ]);

    $services->set(PsrHttpFactory::class)
        ->args([
            service(Psr17Factory::class),
            service(Psr17Factory::class),
            service(Psr17Factory::class),
            service(Psr17Factory::class),
        ]);

    $services->set(Psr17Factory::class);

    $services->set(HappyPathValidator::class)
        ->decorate('validator')
        ->args([
            service(HappyPathValidator::class . '.inner'),
        ]);

    $services->set(CustomSnippetFormatController::class)
        ->public()
        ->args([
            service(KernelPluginCollection::class),
            service('twig'),
        ]);

    $services->set(FeatureFlagController::class)
        ->public()
        ->args([
            service(FeatureFlagRegistry::class),
            service(CacheClearer::class),
        ]);
};
