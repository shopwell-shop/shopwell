<?php declare(strict_types=1);

namespace Shopwell\Core\System\DependencyInjection;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopwell\Core\System\StateMachine\Aggregation\StateMachineHistory\StateMachineHistoryDefinition;
use Shopwell\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateDefinition;
use Shopwell\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateTranslationDefinition;
use Shopwell\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionDefinition;
use Shopwell\Core\System\StateMachine\Api\StateMachineActionController;
use Shopwell\Core\System\StateMachine\Command\WorkflowDumpCommand;
use Shopwell\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Shopwell\Core\System\StateMachine\StateMachineDefinition;
use Shopwell\Core\System\StateMachine\StateMachineLocker;
use Shopwell\Core\System\StateMachine\StateMachineRegistry;
use Shopwell\Core\System\StateMachine\StateMachineTransitionValidator;
use Shopwell\Core\System\StateMachine\StateMachineTranslationDefinition;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(StateMachineActionController::class)
        ->public()
        ->args([
            service(StateMachineRegistry::class),
            service(DefinitionInstanceRegistry::class),
        ])
        ->call('setContainer', [
            service('service_container'),
        ]);

    $services->set(StateMachineRegistry::class)
        ->args([
            service('state_machine.repository'),
            service('state_machine_state.repository'),
            service('state_machine_history.repository'),
            service('event_dispatcher'),
            service(DefinitionInstanceRegistry::class),
            service(StateMachineLocker::class),
            service(Connection::class),
        ])
        ->tag('kernel.reset', ['method' => 'reset']);

    // @deprecated tag:v6.8.0 - remove the registration together with StateMachineTransitionValidator
    $services->set(StateMachineTransitionValidator::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('kernel.event_subscriber')
        ->tag('shopwell.inactiveFeature', ['flag' => 'v6.8.0.0']);

    $services->set(StateMachineLocker::class)
        ->args([
            service('lock.factory'),
        ])
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(WorkflowDumpCommand::class)
        ->args([
            service(StateMachineRegistry::class),
        ])
        ->tag('console.command');

    $services->set(StateMachineDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(StateMachineTranslationDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(StateMachineStateDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(StateMachineStateTranslationDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(StateMachineTransitionDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(StateMachineHistoryDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(InitialStateIdLoader::class)
        ->args([
            service(Connection::class),
            service('cache.object'),
        ])
        ->tag('kernel.reset', ['method' => 'reset']);
};
