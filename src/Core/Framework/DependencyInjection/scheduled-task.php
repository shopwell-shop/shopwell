<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\DependencyInjection;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Shopwell\Core\Framework\MessageQueue\Api\ScheduledTaskController;
use Shopwell\Core\Framework\MessageQueue\Command\DeactivateScheduledTaskCommand;
use Shopwell\Core\Framework\MessageQueue\Command\ListScheduledTaskCommand;
use Shopwell\Core\Framework\MessageQueue\Command\RegisterScheduledTasksCommand;
use Shopwell\Core\Framework\MessageQueue\Command\RunSingleScheduledTaskCommand;
use Shopwell\Core\Framework\MessageQueue\Command\ScheduledTaskRunner;
use Shopwell\Core\Framework\MessageQueue\Command\ScheduleScheduledTaskCommand;
use Shopwell\Core\Framework\MessageQueue\ScheduledTask\MessageQueue\RegisterScheduledTaskHandler;
use Shopwell\Core\Framework\MessageQueue\ScheduledTask\Registry\TaskRegistry;
use Shopwell\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskDefinition;
use Shopwell\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskExecutor;
use Shopwell\Core\Framework\MessageQueue\ScheduledTask\Scheduler\TaskRunner;
use Shopwell\Core\Framework\MessageQueue\ScheduledTask\Scheduler\TaskScheduler;
use Shopwell\Core\Framework\MessageQueue\ScheduledTask\SymfonyBridge\ScheduleProvider;
use Shopwell\Core\Framework\MessageQueue\ScheduledTask\Telemetry\ScheduledTaskHealthCollector;
use Shopwell\Core\Framework\MessageQueue\ScheduledTask\Telemetry\ScheduledTaskHealthGateway;
use Shopwell\Core\Framework\MessageQueue\ScheduledTask\Telemetry\ScheduledTaskMetricsSubscriber;
use Shopwell\Core\Framework\MessageQueue\ScheduledTask\Telemetry\TaskNameResolver;
use Shopwell\Core\Framework\MessageQueue\Subscriber\PluginLifecycleSubscriber;
use Shopwell\Core\Framework\MessageQueue\Subscriber\UpdatePostFinishSubscriber;
use Shopwell\Core\Framework\MessageQueue\Telemetry\WorkerMessageTimingHelper;
use Shopwell\Core\Framework\Telemetry\Metrics\Meter;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(ScheduledTaskDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(ScheduledTaskHealthGateway::class)
        ->args([
            service(Connection::class),
        ]);

    $services->set(ScheduledTaskHealthCollector::class)
        ->args([
            service(ScheduledTaskHealthGateway::class),
            service(ClockInterface::class),
        ])
        ->tag('shopwell.telemetry.periodic_metric_collector');

    $services->set(TaskNameResolver::class);

    $services->set(ScheduledTaskMetricsSubscriber::class)
        ->args([
            service(Meter::class),
            service(TaskNameResolver::class),
            service(WorkerMessageTimingHelper::class),
        ])
        ->tag('kernel.event_subscriber')
        ->tag('shopwell.telemetry.subscriber');

    $services->set(ScheduledTaskExecutor::class)
        ->args([
            service('scheduled_task.repository'),
            service('logger'),
            service(ClockInterface::class),
        ]);

    $services->set(TaskScheduler::class)
        ->args([
            service('scheduled_task.repository'),
            service('messenger.default_bus'),
            service('parameter_bag'),
            service('logger'),
            param('shopwell.messenger.scheduled_task.requeue_timeout'),
            service(ClockInterface::class),
        ]);

    $services->set(TaskRegistry::class)
        ->args([
            tagged_iterator('shopwell.scheduled.task'),
            service('scheduled_task.repository'),
            service('parameter_bag'),
            service(ClockInterface::class),
        ]);

    $services->set(ScheduleProvider::class)
        ->args([
            tagged_iterator('shopwell.scheduled.task'),
            service(Connection::class),
            service('cache.object'),
            service('lock.factory'),
        ])
        ->tag('scheduler.schedule_provider', ['name' => 'shopwell']);

    $services->set(RegisterScheduledTaskHandler::class)
        ->args([
            service(TaskRegistry::class),
        ])
        ->tag('messenger.message_handler');

    $services->set(PluginLifecycleSubscriber::class)
        ->args([
            service(TaskRegistry::class),
            service('cache.messenger.restart_workers_signal'),
            service(ClockInterface::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(TaskRunner::class)
        ->args([
            tagged_iterator('messenger.message_handler'),
            service('scheduled_task.repository'),
            service(ClockInterface::class),
        ]);

    $services->set(RegisterScheduledTasksCommand::class)
        ->args([
            service(TaskRegistry::class),
        ])
        ->tag('console.command');

    $services->set(ScheduledTaskRunner::class)
        ->args([
            service(TaskScheduler::class),
            service('cache.messenger.restart_workers_signal'),
            service(ClockInterface::class),
        ])
        ->tag('console.command');

    $services->set(ListScheduledTaskCommand::class)
        ->args([
            service(TaskRegistry::class),
        ])
        ->tag('console.command');

    $services->set(RunSingleScheduledTaskCommand::class)
        ->args([
            service(TaskRunner::class),
        ])
        ->tag('console.command');

    $services->set(DeactivateScheduledTaskCommand::class)
        ->args([
            service(TaskRegistry::class),
        ])
        ->tag('console.command');

    $services->set(ScheduleScheduledTaskCommand::class)
        ->args([
            service(TaskRegistry::class),
        ])
        ->tag('console.command');

    $services->set(ScheduledTaskController::class)
        ->public()
        ->args([
            service(TaskScheduler::class),
        ])
        ->call('setContainer', [
            service('service_container'),
        ]);

    $services->set(UpdatePostFinishSubscriber::class)
        ->args([
            service(TaskRegistry::class),
        ])
        ->tag('kernel.event_subscriber');
};
