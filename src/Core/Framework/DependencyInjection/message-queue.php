<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\DependencyInjection;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Shopwell\Core\Framework\Adapter\Doctrine\Messenger\DoctrineTransportFactory;
use Shopwell\Core\Framework\Adapter\Messenger\Middleware\QueuedTimeMiddleware;
use Shopwell\Core\Framework\MessageQueue\Api\ConsumeMessagesController;
use Shopwell\Core\Framework\MessageQueue\Middleware\RoutingOverwriteMiddleware;
use Shopwell\Core\Framework\MessageQueue\SendEmailMessageJsonSerializer;
use Shopwell\Core\Framework\MessageQueue\Service\MessageSizeCalculator;
use Shopwell\Core\Framework\MessageQueue\Stats\MySQLStatsRepository;
use Shopwell\Core\Framework\MessageQueue\Stats\StatsService;
use Shopwell\Core\Framework\MessageQueue\Subscriber\EarlyReturnMessagesListener;
use Shopwell\Core\Framework\MessageQueue\Subscriber\MessageQueueSizeRestrictListener;
use Shopwell\Core\Framework\MessageQueue\Subscriber\MessageQueueStatsSubscriber;
use Shopwell\Core\Framework\MessageQueue\Telemetry\MessageGroupResolver;
use Shopwell\Core\Framework\MessageQueue\Telemetry\MessageQueueTelemetrySubscriber;
use Shopwell\Core\Framework\MessageQueue\Telemetry\MessengerQueueDepthCollector;
use Shopwell\Core\Framework\MessageQueue\Telemetry\WorkerMessageTimingHelper;
use Shopwell\Core\Framework\Telemetry\Metrics\Meter;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Messenger\Event\SendMessageToTransportsEvent;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(EarlyReturnMessagesListener::class);

    $services->set(MessageQueueSizeRestrictListener::class)
        ->args([
            service(MessageSizeCalculator::class),
            param('shopware.messenger.enforce_message_size'),
            param('shopware.messenger.message_max_kib_size'),
        ])
        ->tag('kernel.event_listener', ['event' => SendMessageToTransportsEvent::class]);

    $services->set(MessageQueueStatsSubscriber::class)
        ->args([
            service('shopware.increment.gateway.registry'),
            service(StatsService::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(MessageGroupResolver::class);

    $services->set(WorkerMessageTimingHelper::class);

    $services->set(MessageQueueTelemetrySubscriber::class)
        ->args([
            service(Meter::class),
            service(MessageSizeCalculator::class),
            service(MessageGroupResolver::class),
            service(WorkerMessageTimingHelper::class),
        ])
        ->tag('kernel.event_subscriber')
        ->tag('shopware.telemetry.subscriber');

    $services->set(MessengerQueueDepthCollector::class)
        ->args([
            service('messenger.receiver_locator'),
            service('logger'),
        ])
        ->tag('shopware.telemetry.periodic_metric_collector');

    // Controller
    $services->set(ConsumeMessagesController::class)
        ->public()
        ->args([
            service('messenger.receiver_locator'),
            service('messenger.default_bus'),
            service('messenger.listener.stop_worker_on_restart_signal_listener'),
            service(EarlyReturnMessagesListener::class),
            service(MessageQueueStatsSubscriber::class),
            param('messenger.default_transport_name'),
            param('shopware.admin_worker.memory_limit'),
            param('shopware.admin_worker.poll_interval'),
            service('lock.factory'),
        ])
        ->call('setContainer', [
            service('service_container'),
        ]);

    $services->set('messenger.transport.doctrine.factory', DoctrineTransportFactory::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('messenger.transport_factory');

    $services->set(SendEmailMessageJsonSerializer::class)
        ->tag('serializer.normalizer');

    $services->set(MessageSizeCalculator::class)
        ->args([
            service('messenger.default_serializer'),
        ]);

    $services->set(RoutingOverwriteMiddleware::class)
        ->args([
            param('shopware.messenger.routing_overwrite'),
        ]);

    $services->set(MySQLStatsRepository::class)
        ->args([
            service(Connection::class),
            param('shopware.messenger.stats.time_span'),
        ]);

    $services->set(StatsService::class)
        ->args([
            service(MySQLStatsRepository::class),
            param('shopware.messenger.stats.enabled'),
            service(ClockInterface::class),
        ]);

    $services->set(QueuedTimeMiddleware::class);
};
