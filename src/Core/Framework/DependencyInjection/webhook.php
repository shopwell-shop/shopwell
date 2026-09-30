<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\DependencyInjection;

use Doctrine\DBAL\Connection;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use Psr\Clock\ClockInterface;
use Shopwell\Core\Content\Media\File\TrustedUrlResolver;
use Shopwell\Core\Framework\Api\Serializer\JsonEntityEncoder;
use Shopwell\Core\Framework\App\AppLocaleProvider;
use Shopwell\Core\Framework\App\DeletedApps\DeletedAppsGateway;
use Shopwell\Core\Framework\App\Hmac\Guzzle\AuthMiddleware;
use Shopwell\Core\Framework\App\Http\AppSystemHttpMiddleware;
use Shopwell\Core\Framework\App\Payload\AppPayloadServiceHelper;
use Shopwell\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopwell\Core\Framework\Event\BusinessEventCollector;
use Shopwell\Core\Framework\Event\BusinessEventRegistry;
use Shopwell\Core\Framework\Webhook\Authorization\Policy\NotHookablePolicy;
use Shopwell\Core\Framework\Webhook\Authorization\Policy\PolicyRegistry;
use Shopwell\Core\Framework\Webhook\BusinessEventEncoder;
use Shopwell\Core\Framework\Webhook\Command\WebhookDrainToAsyncCommand;
use Shopwell\Core\Framework\Webhook\EventLog\WebhookEventLogDefinition;
use Shopwell\Core\Framework\Webhook\Handler\WebhookEventMessageHandler;
use Shopwell\Core\Framework\Webhook\Hookable\CoreHookableEventDescriber;
use Shopwell\Core\Framework\Webhook\Hookable\HookableEventCollector;
use Shopwell\Core\Framework\Webhook\Hookable\HookableEventFactory;
use Shopwell\Core\Framework\Webhook\Hookable\WriteResultMerger;
use Shopwell\Core\Framework\Webhook\Outbox\RetryDelayCalculator;
use Shopwell\Core\Framework\Webhook\Outbox\StreamLockService;
use Shopwell\Core\Framework\Webhook\Outbox\WebhookOutboxStore;
use Shopwell\Core\Framework\Webhook\ScheduledTask\CleanupWebhookEventLogTask;
use Shopwell\Core\Framework\Webhook\ScheduledTask\CleanupWebhookEventLogTaskHandler;
use Shopwell\Core\Framework\Webhook\Service\WebhookCleanup;
use Shopwell\Core\Framework\Webhook\Service\WebhookClient;
use Shopwell\Core\Framework\Webhook\Service\WebhookDeliveryService;
use Shopwell\Core\Framework\Webhook\Service\WebhookHealthService;
use Shopwell\Core\Framework\Webhook\Service\WebhookLoader;
use Shopwell\Core\Framework\Webhook\Service\WebhookManager;
use Shopwell\Core\Framework\Webhook\Service\WebhookSigningSecretResolver;
use Shopwell\Core\Framework\Webhook\Subscriber\RetryWebhookMessageFailedSubscriber;
use Shopwell\Core\Framework\Webhook\Transport\MySQLWebhookReceiver;
use Shopwell\Core\Framework\Webhook\Transport\WebhookTransportFactory;
use Shopwell\Core\Framework\Webhook\Validation\WebhookTargetValidator;
use Shopwell\Core\Framework\Webhook\Validation\WebhookUrlWriteValidator;
use Shopwell\Core\Framework\Webhook\WebhookCacheClearer;
use Shopwell\Core\Framework\Webhook\WebhookDefinition;
use Shopwell\Core\Framework\Webhook\WebhookDispatcher;
use Shopwell\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Clock\ClockInterface as SymfonyClockInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Messenger\MessageBusInterface;

use function Symfony\Component\DependencyInjection\Loader\Configurator\env;
use function Symfony\Component\DependencyInjection\Loader\Configurator\inline_service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service_closure;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(WebhookDispatcher::class)
        ->decorate('event_dispatcher', null, 100)
        ->args([
            service(WebhookDispatcher::class . '.inner'),
            service(WebhookManager::class),
        ]);

    $services->set(WebhookLoader::class)
        ->args([
            service(Connection::class),
        ]);

    $services->set('shopwell.webhook.guzzle', Client::class)
        ->lazy()
        ->args([
            [
                'timeout' => 20,
                'connect_timeout' => 10,
                'allow_redirects' => AuthMiddleware::ALLOW_REDIRECTS,
                'handler' => inline_service(HandlerStack::class)
                    ->factory([HandlerStack::class, 'create'])
                    ->call('after', [
                        'allow_redirects',
                        service('shopwell.webhook.guzzle.security_middleware'),
                        'app_system_http_security',
                    ])
                    ->call('push', [
                        service('shopwell.app_system.guzzle.middleware'),
                    ]),
            ],
        ]);

    $services->set(WebhookClient::class)
        ->args([
            service('shopwell.webhook.guzzle'),
            service(SymfonyClockInterface::class),
        ]);

    $services->set('shopwell.webhook.trusted_url_resolver', TrustedUrlResolver::class)
        ->args([
            null,
            true,
            param('shopwell.app_system.allowed_private_ip_addresses'),
        ]);

    $services->set('shopwell.webhook.guzzle.security_middleware', AppSystemHttpMiddleware::class)
        ->args([
            service('shopwell.webhook.trusted_url_resolver'),
            param('shopwell.app_system.allow_unencrypted_traffic'),
            true,
            param('shopwell.app_system.allowed_private_ip_addresses'),
            param('shopwell.app_system.enable_url_validation'),
        ]);

    $services->set(WebhookTargetValidator::class)
        ->args([
            param('shopwell.app_system.allow_unencrypted_traffic'),
            param('shopwell.app_system.allowed_private_ip_addresses'),
            service('shopwell.webhook.trusted_url_resolver'),
            param('shopwell.app_system.enable_url_validation'),
        ]);

    $services->set(WebhookUrlWriteValidator::class)
        ->args([
            service(WebhookTargetValidator::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(WebhookOutboxStore::class)
        ->args([
            service(Connection::class),
            service(SymfonyClockInterface::class),
        ]);

    $services->set(RetryDelayCalculator::class)
        ->args([
            service(SymfonyClockInterface::class),
        ]);

    $services->set(StreamLockService::class)
        ->args([
            service(Connection::class),
            service(SymfonyClockInterface::class),
        ]);

    $services->set(WebhookHealthService::class)
        ->args([
            service(Connection::class),
        ]);

    $services->set(MySQLWebhookReceiver::class)
        ->args([
            service(StreamLockService::class),
            service(WebhookOutboxStore::class),
            service(SymfonyClockInterface::class),
            service('logger'),
        ])
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(WebhookTransportFactory::class)
        ->args([
            service(WebhookOutboxStore::class),
            service_closure('messenger.transport.async'),
            service_closure(MySQLWebhookReceiver::class),
        ])
        ->tag('messenger.transport_factory');

    $services->set(WebhookDrainToAsyncCommand::class)
        ->args([
            service(Connection::class),
            service(MessageBusInterface::class),
            service('logger'),
        ])
        ->tag('console.command')
        ->tag('shopwell.inactiveFeature', ['flag' => 'v6.8.0.0']);

    $services->set(WebhookManager::class)
        ->lazy()
        ->args([
            service(WebhookLoader::class),
            service(HookableEventFactory::class),
            service(AppLocaleProvider::class),
            service(AppPayloadServiceHelper::class),
            service(WebhookClient::class),
            service(MessageBusInterface::class),
            env('APP_URL'),
            param('kernel.shopwell_version'),
            param('shopwell.admin_worker.enable_admin_worker'),
            service(WebhookDeliveryService::class),
            service(WebhookOutboxStore::class),
            service(PolicyRegistry::class),
        ]);

    $services->set(WebhookCacheClearer::class)
        ->args([
            service(WebhookManager::class),
        ])
        ->tag('kernel.event_subscriber')
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(PolicyRegistry::class)
        ->args([
            tagged_iterator('shopwell.webhook.policy'),
            service('logger'),
        ]);

    $services->set(NotHookablePolicy::class)
        ->args([service(BusinessEventRegistry::class)])
        ->tag('shopwell.webhook.policy');

    $services->set(HookableEventFactory::class)
        ->lazy()
        ->args([
            service(BusinessEventEncoder::class),
            service(WriteResultMerger::class),
            service(HookableEventCollector::class),
        ]);

    $services->set(WriteResultMerger::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
        ]);

    $services->set(BusinessEventEncoder::class)
        ->args([
            service(JsonEntityEncoder::class),
            service(DefinitionInstanceRegistry::class),
        ]);

    $services->set(WebhookDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(WebhookEventLogDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(HookableEventCollector::class)
        ->args([
            service(BusinessEventCollector::class),
            service(DefinitionInstanceRegistry::class),
            tagged_iterator('shopwell.entity.hookable'),
            tagged_iterator('shopwell.hookable_event.describer'),
        ]);

    $services->set(CoreHookableEventDescriber::class)
        ->tag('shopwell.hookable_event.describer');

    $services->set(WebhookSigningSecretResolver::class)
        ->args([
            service(Connection::class),
            service(DeletedAppsGateway::class),
        ]);

    $services->set(WebhookDeliveryService::class)
        ->args([
            service(WebhookClient::class),
            service(AppPayloadServiceHelper::class),
            service(WebhookSigningSecretResolver::class),
            service(WebhookOutboxStore::class),
            service(RetryDelayCalculator::class),
            service(MessageBusInterface::class),
            service(WebhookHealthService::class),
            service('logger'),
            param('shopwell.admin_worker.enable_admin_worker'),
            param('shopwell.webhook.failure_strategy'),
        ]);

    $services->set(WebhookEventMessageHandler::class)
        ->args([
            service(WebhookClient::class),
            service(WebhookHealthService::class),
            service(WebhookOutboxStore::class),
            service(WebhookDeliveryService::class),
            service('logger'),
        ])
        ->tag('messenger.message_handler');

    $services->set(RetryWebhookMessageFailedSubscriber::class)
        ->args([
            service(Connection::class),
            service(WebhookOutboxStore::class),
            param('shopwell.webhook.failure_strategy'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(WebhookCleanup::class)
        ->args([
            service(SystemConfigService::class),
            service(Connection::class),
            service(StreamLockService::class),
            service(SymfonyClockInterface::class),
            service(ClockInterface::class),
        ]);

    $services->set(CleanupWebhookEventLogTask::class)
        ->tag('shopwell.scheduled.task');

    $services->set(CleanupWebhookEventLogTaskHandler::class)
        ->args([
            service('scheduled_task.repository'),
            service('logger'),
            service(WebhookCleanup::class),
        ])
        ->tag('messenger.message_handler');
};
