<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Mcp\Resource;

use Mcp\Capability\Attribute\McpResource;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Util\Json;
use Shopwell\Core\System\StateMachine\StateMachineCollection;

/**
 * @experimental stableVersion:v6.8.0
 */
#[Package('framework')]
#[McpResource(
    uri: 'shopwell://state-machines',
    name: 'shopwell-state-machines',
    description: 'All state machines with their states and transitions. Use this to understand valid actions for shopwell-order-state.'
)]
class StateMachineResource
{
    /**
     * @internal
     *
     * @param EntityRepository<StateMachineCollection> $stateMachineRepository
     */
    public function __construct(
        private readonly EntityRepository $stateMachineRepository,
    ) {
    }

    /**
     * @return array{uri: string, mimeType: string, text: string}
     */
    public function __invoke(): array
    {
        $criteria = new Criteria();
        $criteria->addAssociation('states');
        $criteria->addAssociation('transitions.fromStateMachineState');
        $criteria->addAssociation('transitions.toStateMachineState');

        $result = $this->stateMachineRepository->search($criteria, Context::createDefaultContext());

        $machines = [];
        foreach ($result->getEntities() as $machine) {
            $states = [];
            foreach ($machine->getStates() ?? [] as $state) {
                $states[] = [
                    'technicalName' => $state->getTechnicalName(),
                    'name' => $state->getName(),
                ];
            }

            $transitions = [];
            foreach ($machine->getTransitions() ?? [] as $transition) {
                $transitions[] = [
                    'actionName' => $transition->getActionName(),
                    'fromState' => $transition->getFromStateMachineState()?->getTechnicalName(),
                    'toState' => $transition->getToStateMachineState()?->getTechnicalName(),
                ];
            }

            $machines[] = [
                'technicalName' => $machine->getTechnicalName(),
                'name' => $machine->getName(),
                'states' => $states,
                'transitions' => $transitions,
            ];
        }

        return [
            'uri' => 'shopwell://state-machines',
            'mimeType' => 'application/json',
            'text' => Json::encode($machines),
        ];
    }
}
