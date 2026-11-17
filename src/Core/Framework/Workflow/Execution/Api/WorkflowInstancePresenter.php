<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Api;

use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\Definition\WorkflowExecutionDefinitionCollection;
use Shopwell\Core\Framework\Workflow\Execution\Graph\GraphData;
use Shopwell\Core\Framework\Workflow\Execution\Log\WorkflowLogCollection;
use Shopwell\Core\Framework\Workflow\Execution\Node\WorkflowNodeExecutionCollection;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceEntity;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceStates;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('framework')]
final readonly class WorkflowInstancePresenter
{
    /**
     * @param EntityRepository<WorkflowExecutionDefinitionCollection> $definitions
     * @param EntityRepository<WorkflowNodeExecutionCollection> $executions
     * @param EntityRepository<WorkflowLogCollection> $logs
     */
    public function __construct(
        private WorkflowUserLookup $users,
        private EntityRepository $definitions,
        private EntityRepository $executions,
        private EventDispatcherInterface $events,
        private EntityRepository $logs,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function present(WorkflowInstanceEntity $instance, Context $context, bool $withDetail = false): array
    {
        $names = $this->users->names([$instance->initiatorId], $context);

        return $this->presentWithNames($instance, $context, $names, $withDetail);
    }

    /**
     * @param iterable<WorkflowInstanceEntity> $instances
     *
     * @return list<array<string, mixed>>
     */
    public function presentMany(iterable $instances, Context $context): array
    {
        $rows = [];
        $initiatorIds = [];

        foreach ($instances as $instance) {
            $rows[] = $instance;
            $initiatorIds[] = $instance->initiatorId;
        }

        $names = $this->users->names($initiatorIds, $context);

        return array_map(fn (WorkflowInstanceEntity $instance): array => $this->presentWithNames($instance, $context, $names, withDetail: false), $rows);
    }

    /**
     * @param array<string, string> $names
     *
     * @return array<string, mixed>
     */
    private function presentWithNames(WorkflowInstanceEntity $instance, Context $context, array $names, bool $withDetail): array
    {
        $payload = [
            'id' => $instance->id,
            'number' => $instance->number,
            'title' => $instance->title,
            'status' => WorkflowInstanceStates::CONTRACT_STATUS[$instance->state()] ?? $instance->state(),
            'result' => $instance->result,
            'definitionId' => $instance->definitionId,
            'definitionVersion' => $instance->definitionVersion,
            'initiator' => ['id' => $instance->initiatorId, 'name' => $names[$instance->initiatorId] ?? $instance->initiatorId],
            'currentNodeId' => $instance->currentNodeId,
            'round' => $instance->round,
            'startedAt' => $instance->startedAt?->format(\DateTimeInterface::ATOM),
            'endedAt' => $instance->endedAt?->format(\DateTimeInterface::ATOM),
            'formData' => $instance->form(),
            'canCancel' => ($instance->isRunning() || $instance->state() === WorkflowInstanceStates::RETURNED)
                && $context->getSource() instanceof AdminApiSource
                && $context->getSource()->getUserId() === $instance->initiatorId
                && $context->isAllowed('workflow_instance:start'),
        ];

        if ($withDetail) {
            $payload = [...$payload, ...$this->presentDetails($instance, $context)];
        }

        $event = new WorkflowInstancePresentationEvent($instance, $context, $payload, $withDetail);
        $this->events->dispatch($event);

        return $event->getData();
    }

    /**
     * @return array<string, mixed>
     */
    private function presentDetails(WorkflowInstanceEntity $instance, Context $context): array
    {
        $graph = GraphData::fromArray($instance->snapshot());
        $schema = $graph->singleStartNode()->property('inputSchema');

        if (!\is_array($schema)) {
            $schema = $this->definitions->search(new Criteria([$instance->definitionId]), $context)
                ->getEntities()->first()?->formFields() ?? [];
        }

        $executions = $this->presentExecutions($instance->id, $context);
        $nodeIds = [];
        $edgeIds = [];

        foreach ($executions as $execution) {
            if ($execution['round'] !== $instance->round) {
                continue;
            }

            $nodeIds[] = $execution['nodeId'];

            if ($execution['sourceConnectionId'] !== null) {
                $edgeIds[] = $execution['sourceConnectionId'];
            }
        }

        return [
            'graph' => $instance->snapshot(),
            'formSchema' => $schema,
            'executions' => $executions,
            'selectedPath' => ['nodeIds' => $nodeIds, 'edgeIds' => $edgeIds],
            'logs' => $this->presentLogs($instance->id, $context),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function presentExecutions(string $instanceId, Context $context): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('instanceId', $instanceId));
        $criteria->addSorting(new FieldSorting('sequence', FieldSorting::ASCENDING));
        $executions = $this->executions->search($criteria, $context)->getEntities();
        $rows = [];

        foreach ($executions as $execution) {
            $rows[] = [
                'id' => $execution->id,
                'nodeId' => $execution->nodeId,
                'sequence' => $execution->sequence,
                'round' => $execution->round,
                'status' => $execution->status,
                'sourceConnectionId' => $execution->sourceConnectionId,
                'waitingFor' => $execution->waitingFor,
                'output' => $execution->output ?? [],
                'result' => $execution->result,
                'startedAt' => $execution->startedAt?->format(\DateTimeInterface::ATOM),
                'endedAt' => $execution->endedAt?->format(\DateTimeInterface::ATOM),
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function presentLogs(string $instanceId, Context $context): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('instanceId', $instanceId));
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::ASCENDING));
        $logs = $this->logs->search($criteria, $context)->getEntities();
        $operatorIds = [];

        foreach ($logs as $log) {
            if ($log->operatorId !== null) {
                $operatorIds[] = $log->operatorId;
            }
        }

        $names = $this->users->names($operatorIds, $context);
        $rows = [];

        foreach ($logs as $log) {
            $rows[] = [
                'id' => $log->id,
                'action' => $log->action,
                'logType' => $log->logType,
                'operator' => $log->operatorId === null ? null : [
                    'id' => $log->operatorId,
                    'name' => $names[$log->operatorId] ?? $log->operatorId,
                ],
                'comment' => $log->comment,
                'payload' => $log->payload ?? [],
                'createdAt' => $log->getCreatedAt()?->format(\DateTimeInterface::ATOM),
            ];
        }

        return $rows;
    }
}
