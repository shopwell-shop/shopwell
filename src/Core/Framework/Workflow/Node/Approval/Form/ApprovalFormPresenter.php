<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Node\Approval\Form;

use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\Api\WorkflowInstancePresentationEvent;
use Shopwell\Core\Framework\Workflow\Execution\Api\WorkflowUserLookup;
use Shopwell\Core\Framework\Workflow\Execution\Copy\WorkflowCopyCollection;
use Shopwell\Core\Framework\Workflow\Execution\Graph\GraphData;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceEntity;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceStates;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskFinder;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Project through one explicit task or copy context. Never union visibility across nodes.
 *
 * @phpstan-type FormContext array{id: string, nodeId: string, nodeName: string, kind: string, round: int, editable: bool}
 * @phpstan-type Permissions array<string, array{access: string, required: bool}>
 *
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\Framework\Workflow\Node\Approval\Form\ApprovalFormTest
 */
#[Package('framework')]
final readonly class ApprovalFormPresenter implements EventSubscriberInterface
{
    /**
     * @param EntityRepository<WorkflowCopyCollection> $copies
     */
    public function __construct(
        private ApprovalFormService $forms,
        private ApprovalFormStore $store,
        private WorkflowTaskFinder $tasks,
        private EntityRepository $copies,
        private RequestStack $requests,
        private WorkflowUserLookup $users,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [WorkflowInstancePresentationEvent::class => ['present', -100]];
    }

    public function present(WorkflowInstancePresentationEvent $event): void
    {
        $instance = $event->instance;
        $graph = GraphData::fromArray($instance->snapshot());
        if (!$this->containsApproval($graph)) {
            return;
        }

        $source = $event->context->getSource();
        $actorId = $source instanceof AdminApiSource ? $source->getUserId() : null;
        $contexts = [];
        $selected = null;
        $permissions = [];
        $editable = false;

        if ($actorId !== null) {
            $isInitiator = $instance->initiatorId === $actorId;
            $returned = $instance->state() === WorkflowInstanceStates::RETURNED;
            if ($event->withDetail && !($returned && $isInitiator)) {
                $contexts = $this->viewerContexts($instance, $actorId, $event->context, $graph);
                $selected = $this->selectContext($contexts);
            }
            if ($selected !== null) {
                $permissions = $this->forms->permissions($instance, $selected['nodeId'], $actorId, $event->context);
                $editable = $selected['editable'];
            } elseif ($isInitiator && $contexts === []) {
                $permissions = $this->forms->permissions($instance, null, $actorId, $event->context, initiator: true);
                $editable = $event->withDetail && $returned;
            }
        }

        if (!$editable) {
            $permissions = $this->readOnly($permissions);
        }
        $visible = [];
        foreach ($permissions as $key => $permission) {
            if ($permission['access'] !== ApprovalFormPolicy::HIDDEN) {
                $visible[$key] = true;
            }
        }
        $schema = array_values(array_filter($this->forms->schema($instance), static fn (array $field): bool => isset($visible[$field['key']])));

        if ($event->withDetail) {
            $event->addData([
                'formVersion' => $instance->ext[ApprovalFormService::VERSION_KEY] ?? 0,
                'formPermissions' => array_intersect_key($permissions, $visible),
                'formChanges' => $this->visibleChanges($instance->id, $visible, $event->context),
                'formContexts' => $contexts,
                'formContextId' => $selected['id'] ?? null,
                'formContextRequired' => $selected === null && $contexts !== [],
            ]);
        }

        $event->filterData(fn (array $data): array => $this->project($data, $visible, $schema, $permissions));
    }

    private function containsApproval(GraphData $graph): bool
    {
        foreach ($graph->nodeList() as $node) {
            if ($node->property('approvalStage') === true) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<FormContext>
     */
    private function viewerContexts(WorkflowInstanceEntity $instance, string $actorId, Context $context, GraphData $graph): array
    {
        $contexts = [];
        foreach ($this->tasks->forInstance($instance->id, $context) as $task) {
            if ($task->assigneeId !== $actorId) {
                continue;
            }
            $contexts[] = [
                'id' => $task->id,
                'nodeId' => $task->nodeId,
                'nodeName' => $task->nodeName,
                'kind' => 'task',
                'round' => $task->round,
                'editable' => $task->isTodo() && $instance->isRunning() && $task->round === $instance->round && $task->nodeId === $instance->currentNodeId,
            ];
        }
        $criteria = (new Criteria())->addFilter(new EqualsFilter('instanceId', $instance->id), new EqualsFilter('userId', $actorId));
        foreach ($this->copies->search($criteria, $context)->getEntities() as $copy) {
            $contexts[] = [
                'id' => $copy->id,
                'nodeId' => $copy->nodeId,
                'nodeName' => $graph->node($copy->nodeId)->property('name', $copy->nodeId),
                'kind' => 'copy',
                'round' => $copy->round,
                'editable' => false,
            ];
        }

        return $contexts;
    }

    /**
     * @param list<FormContext> $contexts
     *
     * @return FormContext|null
     */
    private function selectContext(array $contexts): ?array
    {
        $requested = $this->requests->getCurrentRequest()?->query->get('taskId');
        if ($requested !== null) {
            foreach ($contexts as $candidate) {
                if ($candidate['id'] === $requested) {
                    return $candidate;
                }
            }
            throw WorkflowExecutionException::forbidden('The form context does not belong to the current viewer.');
        }

        $pending = array_values(array_filter($contexts, static fn (array $item): bool => $item['editable']));
        $candidates = $pending !== [] ? $pending : $contexts;

        return \count($candidates) === 1 ? $candidates[0] : null;
    }

    /**
     * @param Permissions $permissions
     *
     * @return Permissions
     */
    private function readOnly(array $permissions): array
    {
        foreach ($permissions as &$permission) {
            if ($permission['access'] === ApprovalFormPolicy::EDITABLE) {
                $permission = ['access' => ApprovalFormPolicy::READONLY, 'required' => false];
            }
        }
        unset($permission);

        return $permissions;
    }

    /**
     * @param array<string, true> $visible
     *
     * @return list<array<string, mixed>>
     */
    private function visibleChanges(string $instanceId, array $visible, Context $context): array
    {
        $changes = [];
        foreach ($this->store->history($instanceId) as $change) {
            $change['changes'] = array_intersect_key($change['changes'], $visible);
            if ($change['changes'] !== []) {
                $changes[] = $change;
            }
        }

        $actorIds = array_values(array_unique(array_filter(array_column($changes, 'actorId'))));
        $names = $this->users->names($actorIds, $context);
        foreach ($changes as &$entry) {
            $actorId = $entry['actorId'];
            $entry['actor'] = $actorId === null ? null : ['id' => $actorId, 'name' => $names[$actorId] ?? $actorId];
        }
        unset($entry);

        return $changes;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, true> $visible
     * @param list<array<string, mixed>> $visibleSchema
     * @param Permissions $permissions
     *
     * @return array<string, mixed>
     */
    private function project(array $data, array $visible, array $visibleSchema, array $permissions): array
    {
        $data['formData'] = array_intersect_key($data['formData'] ?? [], $visible);
        if (isset($data['formSchema'])) {
            $data['formSchema'] = $visibleSchema;
        }
        $data['readOnlyInputKeys'] = array_keys(array_filter($permissions, static fn (array $permission): bool => $permission['access'] === ApprovalFormPolicy::READONLY));

        // The diagram needs presentation properties, never executable expressions or input schemas.
        $nodeProperties = array_flip(['name', 'description', 'countersignType', 'approvalStage']);
        foreach ($data['graph']['nodes'] ?? [] as $index => $node) {
            $data['graph']['nodes'][$index]['properties'] = array_intersect_key($node['properties'] ?? [], $nodeProperties);
        }

        $edgeProperties = array_flip(['name', 'sourcePort', 'targetPort', 'result', 'fallback']);
        foreach ($data['graph']['edges'] ?? [] as $index => $edge) {
            $data['graph']['edges'][$index]['properties'] = array_intersect_key($edge['properties'] ?? [], $edgeProperties);
        }

        $logProperties = array_flip(['round', 'previousRound', 'targetUserId', 'sourceTaskId', 'operation']);
        foreach ($data['logs'] ?? [] as $index => $log) {
            $data['logs'][$index]['payload'] = array_intersect_key($log['payload'] ?? [], $logProperties);
        }

        foreach (array_keys($data['executions'] ?? []) as $index) {
            $data['executions'][$index]['output'] = [];
        }

        return $data;
    }
}
