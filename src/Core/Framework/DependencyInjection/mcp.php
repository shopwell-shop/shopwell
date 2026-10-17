<?php declare(strict_types=1);

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Shopwell\Core\Content\Flow\Api\FlowActionCollector;
use Shopwell\Core\Content\Media\Upload\MediaUploadService;
use Shopwell\Core\Framework\Api\Acl\AclCriteriaValidator;
use Shopwell\Core\Framework\Api\OAuth\ClientRepository;
use Shopwell\Core\Framework\Api\Serializer\JsonEntityEncoder;
use Shopwell\Core\Framework\App\Aggregate\AppMcpPrompt\AppMcpPromptDefinition;
use Shopwell\Core\Framework\App\Aggregate\AppMcpPromptTranslation\AppMcpPromptTranslationDefinition;
use Shopwell\Core\Framework\App\Aggregate\AppMcpResource\AppMcpResourceDefinition;
use Shopwell\Core\Framework\App\Aggregate\AppMcpResourceTranslation\AppMcpResourceTranslationDefinition;
use Shopwell\Core\Framework\App\Aggregate\AppMcpTool\AppMcpToolDefinition;
use Shopwell\Core\Framework\App\Aggregate\AppMcpToolTranslation\AppMcpToolTranslationDefinition;
use Shopwell\Core\Framework\App\Lifecycle\Handler\McpLifecycleHandler;
use Shopwell\Core\Framework\App\Lifecycle\Persister\McpPromptPersister;
use Shopwell\Core\Framework\App\Lifecycle\Persister\McpResourcePersister;
use Shopwell\Core\Framework\App\Lifecycle\Persister\McpToolPersister;
use Shopwell\Core\Framework\App\ShopId\ShopIdProvider;
use Shopwell\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopwell\Core\Framework\DependencyInjection\CompilerPass\McpToolDiscoveryCompilerPass;
use Shopwell\Core\Framework\Event\BusinessEventCollector;
use Shopwell\Core\Framework\Mcp\AllowList\McpAllowlistFilter;
use Shopwell\Core\Framework\Mcp\AllowList\McpAllowlistListRequestHandler;
use Shopwell\Core\Framework\Mcp\AllowList\McpAllowlistProvider;
use Shopwell\Core\Framework\Mcp\Authentication\McpAuthenticationListener;
use Shopwell\Core\Framework\Mcp\Authentication\McpExceptionListener;
use Shopwell\Core\Framework\Mcp\Command\DebugMcpCommand;
use Shopwell\Core\Framework\Mcp\Context\McpContextProvider;
use Shopwell\Core\Framework\Mcp\Context\StoreApiMcpContextProvider;
use Shopwell\Core\Framework\Mcp\Controller\IntegrationMcpAllowlistController;
use Shopwell\Core\Framework\Mcp\Controller\McpServerController;
use Shopwell\Core\Framework\Mcp\Controller\McpToolListController;
use Shopwell\Core\Framework\Mcp\Controller\StoreApiMcpServerController;
use Shopwell\Core\Framework\Mcp\Controller\UserMcpAllowlistController;
use Shopwell\Core\Framework\Mcp\Http\McpHttpTransportFactory;
use Shopwell\Core\Framework\Mcp\Loader\AppMcpCapabilityExecutor;
use Shopwell\Core\Framework\Mcp\Loader\AppMcpPrivilegeProvider;
use Shopwell\Core\Framework\Mcp\Loader\AppMcpPromptLoader;
use Shopwell\Core\Framework\Mcp\Loader\AppMcpResourceLoader;
use Shopwell\Core\Framework\Mcp\Loader\AppMcpToolLoader;
use Shopwell\Core\Framework\Mcp\McpAllowedHostsProvider;
use Shopwell\Core\Framework\Mcp\McpCapabilityCatalog;
use Shopwell\Core\Framework\Mcp\McpRequestedToolsetResolver;
use Shopwell\Core\Framework\Mcp\McpToolsetRegistry;
use Shopwell\Core\Framework\Mcp\McpToolsetSessionStorage;
use Shopwell\Core\Framework\Mcp\Notification\AppMcpCapabilityDetector;
use Shopwell\Core\Framework\Mcp\Notification\AppMcpCapabilityLifecycleSubscriber;
use Shopwell\Core\Framework\Mcp\Notification\McpListChangedNotifier;
use Shopwell\Core\Framework\Mcp\Notification\McpListVersions;
use Shopwell\Core\Framework\Mcp\Prompt\ShopwellContextPrompt;
use Shopwell\Core\Framework\Mcp\RateLimit\McpRateLimiter;
use Shopwell\Core\Framework\Mcp\Resource\BusinessEventsResource;
use Shopwell\Core\Framework\Mcp\Resource\CurrencyListResource;
use Shopwell\Core\Framework\Mcp\Resource\EntityListResource;
use Shopwell\Core\Framework\Mcp\Resource\ExtensionsResource;
use Shopwell\Core\Framework\Mcp\Resource\FlowActionsResource;
use Shopwell\Core\Framework\Mcp\Resource\LanguageListResource;
use Shopwell\Core\Framework\Mcp\Resource\SalesChannelListResource;
use Shopwell\Core\Framework\Mcp\Resource\StateMachineResource;
use Shopwell\Core\Framework\Mcp\Resource\ToolResultResource;
use Shopwell\Core\Framework\Mcp\Result\McpToolResultParser;
use Shopwell\Core\Framework\Mcp\Result\McpToolResultPointerSigner;
use Shopwell\Core\Framework\Mcp\Result\McpToolResultRenderer;
use Shopwell\Core\Framework\Mcp\ScheduledTask\McpToolResultCacheCleanupTask;
use Shopwell\Core\Framework\Mcp\ScheduledTask\McpToolResultCacheCleanupTaskHandler;
use Shopwell\Core\Framework\Mcp\ScheduledTask\McpToolsetSessionCleanupTask;
use Shopwell\Core\Framework\Mcp\ScheduledTask\McpToolsetSessionCleanupTaskHandler;
use Shopwell\Core\Framework\Mcp\Session\McpSessionCleanupSubscriber;
use Shopwell\Core\Framework\Mcp\Session\McpSessionIdValidator;
use Shopwell\Core\Framework\Mcp\Tool\EntityAggregateTool;
use Shopwell\Core\Framework\Mcp\Tool\EntityDeleteTool;
use Shopwell\Core\Framework\Mcp\Tool\EntityReadTool;
use Shopwell\Core\Framework\Mcp\Tool\EntitySchemaTool;
use Shopwell\Core\Framework\Mcp\Tool\EntitySearchTool;
use Shopwell\Core\Framework\Mcp\Tool\EntityUpsertTool;
use Shopwell\Core\Framework\Mcp\Tool\MediaUploadTool;
use Shopwell\Core\Framework\Mcp\Tool\OrderStateTool;
use Shopwell\Core\Framework\Mcp\Tool\Search\ToolSearch;
use Shopwell\Core\Framework\Mcp\Tool\SystemConfigReadTool;
use Shopwell\Core\Framework\Mcp\Tool\SystemConfigWriteTool;
use Shopwell\Core\Framework\Mcp\Tool\ToolSearchTool;
use Shopwell\Core\Framework\Mcp\Tool\ToolsetEnableTool;
use Shopwell\Core\Framework\Mcp\Tool\ToolsetsListTool;
use Shopwell\Core\Framework\Mcp\ToolResultCacheStorage;
use Shopwell\Core\Framework\RateLimiter\RateLimiter;
use Shopwell\Core\System\SalesChannel\Mcp\Resource\StoreApiToolResultResource;
use Shopwell\Core\System\SalesChannel\Mcp\Tool\StoreApiContextTool;
use Shopwell\Core\System\SalesChannel\Mcp\Tool\StoreApiToolSearchTool;
use Shopwell\Core\System\SalesChannel\Mcp\Tool\StoreApiToolsetEnableTool;
use Shopwell\Core\System\SalesChannel\Mcp\Tool\StoreApiToolsetsListTool;
use Shopwell\Core\System\StateMachine\StateMachineRegistry;
use Shopwell\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\env;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    // The bundle dropped the flat "mcp.pagination_limit" parameter when it gained multiple servers:
    // the limit became a per-server builder value. Keep one Shopwell-owned parameter as the single
    // source of truth — McpServerBuilderCompilerPass applies it to every server builder, and the
    // allowlist request handlers page with the same number.
    $container->parameters()->set('shopwell.mcp.pagination_limit', 50);

    $services->set(McpListVersions::class)
        ->args([service(Connection::class), service(ClockInterface::class)]);

    $services->set(McpListChangedNotifier::class)
        ->args([
            service('mcp.server.admin.session.store')->nullOnInvalid(),
            service(McpListVersions::class),
            service('logger'),
        ])
        ->tag('monolog.logger', ['channel' => 'mcp']);

    $services->set(AppMcpCapabilityDetector::class)
        ->args([service(Connection::class)]);

    $services->set(AppMcpCapabilityLifecycleSubscriber::class)
        ->args([
            service(AppMcpCapabilityDetector::class),
            service(McpListChangedNotifier::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(McpContextProvider::class)
        ->args([service('request_stack')]);

    $services->set(StoreApiMcpContextProvider::class)
        ->args([service('request_stack')]);

    $services->set(McpAllowlistFilter::class);

    $services->set(McpAllowlistProvider::class)
        ->args([
            service(Connection::class),
            service('request_stack'),
            param('shopwell.mcp.tool_dependencies'),
        ]);

    $services->set(McpAllowlistListRequestHandler::class)
        ->args([
            service('mcp.server.admin.registry'),
            service(McpAllowlistProvider::class),
            param('shopwell.mcp.pagination_limit'),
            param('shopwell.mcp.advertised_tools'),
            service(McpToolsetRegistry::class)->nullOnInvalid(),
            service(McpToolsetSessionStorage::class)->nullOnInvalid(),
            service('request_stack'),
            service(McpRequestedToolsetResolver::class),
        ])
        ->tag('mcp.admin.request_handler');

    $services->set(McpAuthenticationListener::class)
        ->args([
            service(ClientRepository::class),
            service(RateLimiter::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(McpExceptionListener::class)
        ->tag('kernel.event_subscriber');

    $services->set(McpSessionIdValidator::class);

    $services->set(McpRateLimiter::class)
        ->args([service(RateLimiter::class)]);

    $services->set(McpAllowedHostsProvider::class)
        ->args([
            service(Connection::class),
            env('APP_URL'),
        ]);

    $services->set(McpHttpTransportFactory::class)
        ->args([
            service('mcp.psr_http_factory')->nullOnInvalid(),
            service('mcp.psr17_factory')->nullOnInvalid(),
            service('mcp.psr17_factory')->nullOnInvalid(),
            service('mcp.http_foundation_factory')->nullOnInvalid(),
            service(McpAllowedHostsProvider::class),
            service('logger'),
        ])
        ->tag('monolog.logger', ['channel' => 'mcp']);

    $services->set(McpServerController::class)
        ->public()
        ->args([
            service('mcp.server.admin')->nullOnInvalid(),
            service(McpHttpTransportFactory::class),
            service(McpRateLimiter::class),
            service(McpSessionIdValidator::class),
            service(McpAllowlistProvider::class),
            service('logger'),
            service(McpAllowlistFilter::class),
            service(McpListChangedNotifier::class),
        ])
        ->tag('controller.service_arguments')
        ->tag('monolog.logger', ['channel' => 'mcp']);

    // Store-api-scoped discovery stack: second instances of the scope-neutral discovery classes,
    // pointed at the store-api registry and params, so enabling an admin toolset never notifies
    // store-api sessions and vice versa. App capabilities only reach the Admin API server, so
    // store-api lists change per session only (toolset enable) and need no shared list versions.
    $services->set('mcp.store_api.list_changed_notifier', McpListChangedNotifier::class)
        ->args([
            service('mcp.server.store_api.session.store')->nullOnInvalid(),
            null,
            service('logger'),
        ])
        ->tag('monolog.logger', ['channel' => 'mcp']);

    $services->set('mcp.store_api.capability_catalog', McpCapabilityCatalog::class)
        ->args([
            service('mcp.server.store_api.registry')->nullOnInvalid(),
            service(AppMcpPrivilegeProvider::class),
            param('shopwell.store_api_mcp.tool_dependencies'),
            param('shopwell.store_api_mcp.tool_privileges'),
            param('shopwell.store_api_mcp.tool_groups'),
        ]);

    $services->set('mcp.store_api.toolset_registry', McpToolsetRegistry::class)
        ->args([service('mcp.store_api.capability_catalog')]);

    $services->set('mcp.store_api.list_request_handler', McpAllowlistListRequestHandler::class)
        ->args([
            service('mcp.server.store_api.registry'),
            null,
            param('shopwell.mcp.pagination_limit'),
            param('shopwell.store_api_mcp.advertised_tools'),
            service('mcp.store_api.toolset_registry'),
            service(McpToolsetSessionStorage::class),
            service('request_stack'),
            service(McpRequestedToolsetResolver::class),
        ])
        ->tag('mcp.store_api.request_handler');

    $services->set(StoreApiMcpServerController::class)
        ->public()
        ->args([
            service('mcp.server.store_api')->nullOnInvalid(),
            service(McpHttpTransportFactory::class),
            service(McpRateLimiter::class),
            service(McpSessionIdValidator::class),
            service('logger'),
            service('mcp.store_api.list_changed_notifier'),
        ])
        ->tag('controller.service_arguments')
        ->tag('monolog.logger', ['channel' => 'mcp']);

    $services->set(AppMcpPrivilegeProvider::class)
        ->args([service(Connection::class), service('logger')])
        ->tag('monolog.logger', ['channel' => 'mcp']);

    $services->set(McpCapabilityCatalog::class)
        ->args([
            service('mcp.server.admin.registry')->nullOnInvalid(),
            service(AppMcpPrivilegeProvider::class),
            param('shopwell.mcp.tool_dependencies'),
            param('shopwell.mcp.tool_privileges'),
            param('shopwell.mcp.tool_groups'),
        ]);

    $services->set(McpToolsetRegistry::class)
        ->args([
            service(McpCapabilityCatalog::class),
            service(McpAllowlistProvider::class),
        ]);

    $services->set(McpToolListController::class)
        ->public()
        ->args([
            service('mcp.server.admin.builder')->nullOnInvalid(),
            service(McpCapabilityCatalog::class)->nullOnInvalid(),
        ])
        ->tag('controller.service_arguments');

    $services->set(IntegrationMcpAllowlistController::class)
        ->public()
        ->args([service('integration.repository')])
        ->tag('controller.service_arguments');

    $services->set(UserMcpAllowlistController::class)
        ->public()
        ->args([service('user.repository')])
        ->tag('controller.service_arguments');

    $services->set(DebugMcpCommand::class)
        ->args([
            service('mcp.server.admin.builder')->nullOnInvalid(),
            service('mcp.server.admin.registry')->nullOnInvalid(),
            service(McpAllowlistProvider::class),
            service(McpCapabilityCatalog::class),
            service('mcp.server.store_api.builder')->nullOnInvalid(),
            service('mcp.server.store_api.registry')->nullOnInvalid(),
            service('mcp.store_api.capability_catalog')->nullOnInvalid(),
            param('mcp.servers.unassigned'),
            param(McpToolDiscoveryCompilerPass::DEMOTED_DISCOVERY_TOOLS_PARAMETER),
        ])
        ->tag('console.command');

    $services->set(McpToolResultRenderer::class)
        ->args([service(ClockInterface::class)]);

    $services->set(McpToolResultParser::class);

    $services->set(McpToolResultPointerSigner::class)
        ->args([param('kernel.secret'), service(ClockInterface::class)]);

    $services->set(ToolResultCacheStorage::class)
        ->args([service(Connection::class), service(ClockInterface::class), service(McpToolResultPointerSigner::class)]);

    $services->set(ToolSearch::class);

    $services->set(McpToolsetSessionStorage::class)
        ->args([service(Connection::class), service(ClockInterface::class)]);

    $services->set(McpToolsetSessionCleanupTask::class)
        ->tag('shopwell.scheduled.task');

    $services->set(McpToolsetSessionCleanupTaskHandler::class)
        ->args([
            service('scheduled_task.repository'),
            service('logger'),
            service(McpToolsetSessionStorage::class),
            service('mcp.server.admin.session.store')->nullOnInvalid(),
            service('mcp.server.store_api.session.store')->nullOnInvalid(),
        ])
        ->tag('messenger.message_handler');

    $services->set(McpToolResultCacheCleanupTask::class)
        ->tag('shopwell.scheduled.task');

    // The monolog.logger tag puts both logger arguments on the mcp channel: the parent's exception
    // logger and the one the handler reports the cleanup outcome on.
    $services->set(McpToolResultCacheCleanupTaskHandler::class)
        ->args([
            service('scheduled_task.repository'),
            service('logger'),
            service(ToolResultCacheStorage::class),
            service(ClockInterface::class),
            service('logger'),
            param('shopwell.mcp.tool_result_cache_ttl'),
        ])
        ->tag('messenger.message_handler')
        ->tag('monolog.logger', ['channel' => 'mcp']);

    $services->set(McpSessionCleanupSubscriber::class)
        ->args([
            service(ToolResultCacheStorage::class),
            service(McpToolsetSessionStorage::class),
        ])
        ->tag('kernel.event_subscriber');

    // Tools
    $services->set(ToolSearchTool::class)
        ->args([
            service('mcp.server.admin.registry')->nullOnInvalid(),
            service(ToolSearch::class),
            service(McpAllowlistProvider::class),
        ])
        ->tag('mcp.tool');

    $services->set(EntitySchemaTool::class)
        ->args([service(DefinitionInstanceRegistry::class)])
        ->tag('mcp.tool');

    $services->set(EntitySearchTool::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
            service('api.request_criteria_builder'),
            service(McpContextProvider::class),
            service(JsonEntityEncoder::class),
            service(AclCriteriaValidator::class),
        ])
        ->tag('mcp.tool');

    $services->set(EntityAggregateTool::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
            service('api.request_criteria_builder'),
            service(McpContextProvider::class),
            service(AclCriteriaValidator::class),
        ])
        ->tag('mcp.tool');

    $services->set(EntityReadTool::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
            service('api.request_criteria_builder'),
            service(McpContextProvider::class),
            service(JsonEntityEncoder::class),
            service(AclCriteriaValidator::class),
        ])
        ->tag('mcp.tool');

    $services->set(SystemConfigReadTool::class)
        ->args([
            service(SystemConfigService::class),
            service(McpContextProvider::class),
        ])
        ->tag('mcp.tool');

    $services->set(EntityUpsertTool::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
            service(McpContextProvider::class),
            service(Connection::class),
        ])
        ->tag('mcp.tool');

    $services->set(EntityDeleteTool::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
            service(McpContextProvider::class),
            service(Connection::class),
        ])
        ->tag('mcp.tool');

    $services->set(SystemConfigWriteTool::class)
        ->args([
            service(SystemConfigService::class),
            service(McpContextProvider::class),
        ])
        ->tag('mcp.tool');

    $services->set(OrderStateTool::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
            service(McpContextProvider::class),
            service(StateMachineRegistry::class),
            service(Connection::class),
        ])
        ->tag('mcp.tool');

    $services->set(MediaUploadTool::class)
        ->args([
            service(MediaUploadService::class),
            service(McpContextProvider::class),
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('mcp.tool');

    $services->set(StoreApiContextTool::class)
        ->args([service(StoreApiMcpContextProvider::class)])
        ->tag('shopwell.store_api_mcp.tool');

    $services->set(StoreApiToolSearchTool::class)
        ->args([
            service('mcp.server.store_api.registry')->nullOnInvalid(),
            service(ToolSearch::class),
            null,
        ])
        ->tag('shopwell.store_api_mcp.tool');

    $services->set(StoreApiToolsetsListTool::class)
        ->args([
            service('mcp.store_api.toolset_registry'),
            service(McpToolsetSessionStorage::class),
            service('request_stack'),
        ])
        ->tag('shopwell.store_api_mcp.tool');

    $services->set(StoreApiToolsetEnableTool::class)
        ->args([
            service('mcp.store_api.toolset_registry'),
            service(McpToolsetSessionStorage::class),
            service('request_stack'),
        ])
        ->tag('shopwell.store_api_mcp.tool');

    $services->set(McpRequestedToolsetResolver::class)
        ->args([service('request_stack')]);

    $services->set(ToolsetsListTool::class)
        ->args([
            service(McpToolsetRegistry::class),
            service(McpToolsetSessionStorage::class),
            service('request_stack'),
        ])
        ->tag('mcp.tool');

    $services->set(ToolsetEnableTool::class)
        ->args([
            service(McpToolsetRegistry::class),
            service(McpToolsetSessionStorage::class),
            service('request_stack'),
        ])
        ->tag('mcp.tool');

    // Prompt
    $services->set(ShopwellContextPrompt::class)
        ->tag('mcp.prompt');

    // Resources
    $services->set(EntityListResource::class)
        ->args([service(DefinitionInstanceRegistry::class)])
        ->tag('mcp.resource');

    $services->set(BusinessEventsResource::class)
        ->args([
            service(BusinessEventCollector::class),
            service(McpContextProvider::class),
        ])
        ->tag('mcp.resource');

    $services->set(FlowActionsResource::class)
        ->args([
            service(FlowActionCollector::class),
            service(McpContextProvider::class),
        ])
        ->tag('mcp.resource');

    $services->set(SalesChannelListResource::class)
        ->args([service('sales_channel.repository')])
        ->tag('mcp.resource');

    $services->set(CurrencyListResource::class)
        ->args([service('currency.repository')])
        ->tag('mcp.resource');

    $services->set(LanguageListResource::class)
        ->args([service('language.repository')])
        ->tag('mcp.resource');

    $services->set(StateMachineResource::class)
        ->args([service('state_machine.repository')])
        ->tag('mcp.resource');

    $services->set(ExtensionsResource::class)
        ->args([
            service(Connection::class),
            service('kernel'),
        ])
        ->tag('mcp.resource');

    $services->set(ToolResultResource::class)
        ->args([service(ToolResultCacheStorage::class), service('request_stack')])
        ->tag('mcp.resource_template');

    $services->set(StoreApiToolResultResource::class)
        ->args([service(ToolResultCacheStorage::class), service('request_stack')])
        ->tag('mcp.resource_template');

    // App MCP Tool pipeline
    $services->set(AppMcpCapabilityExecutor::class)
        ->args([
            service('shopwell.app_system.guzzle'),
            env('APP_URL'),
            service(ShopIdProvider::class),
            param('shopwell.mcp.app_tool_timeout'),
            service('logger'),
            service('kernel'),
            service('request_stack'),
            service('router'),
        ])
        ->tag('monolog.logger', ['channel' => 'mcp']);

    $services->set(AppMcpToolLoader::class)
        ->args([
            service(Connection::class),
            service(AppMcpCapabilityExecutor::class),
            service('logger'),
            param('shopwell.mcp.allowed_tools'),
        ])
        ->tag('mcp.loader');

    $services->set(AppMcpPromptLoader::class)
        ->args([
            service(Connection::class),
            service(AppMcpCapabilityExecutor::class),
            service('logger'),
        ])
        ->tag('mcp.loader')
        ->tag('monolog.logger', ['channel' => 'mcp']);

    $services->set(AppMcpResourceLoader::class)
        ->args([
            service(Connection::class),
            service(AppMcpCapabilityExecutor::class),
            service('logger'),
        ])
        ->tag('mcp.loader')
        ->tag('monolog.logger', ['channel' => 'mcp']);

    $services->set(McpToolPersister::class)
        ->args([service('app_mcp_tool.repository')]);

    $services->set(McpPromptPersister::class)
        ->args([service('app_mcp_prompt.repository')]);

    $services->set(McpResourcePersister::class)
        ->args([service('app_mcp_resource.repository')]);

    $services->set(McpLifecycleHandler::class)
        ->args([
            service(McpToolPersister::class),
            service(McpPromptPersister::class),
            service(McpResourcePersister::class),
            service(AppMcpCapabilityDetector::class),
            service(McpListChangedNotifier::class),
        ])
        ->tag('shopwell.app_lifecycle.handler', ['priority' => -1300]);

    // DAL definitions
    $services->set(AppMcpToolDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(AppMcpToolTranslationDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(AppMcpPromptDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(AppMcpPromptTranslationDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(AppMcpResourceDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(AppMcpResourceTranslationDefinition::class)
        ->tag('shopwell.entity.definition');
};
