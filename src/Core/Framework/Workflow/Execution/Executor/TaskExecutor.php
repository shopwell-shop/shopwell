<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Executor;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Framework\Workflow\Execution\Assignee\AssigneeResolverRegistry;
use Shopwell\Core\Framework\Workflow\Execution\CounterSignResult;
use Shopwell\Core\Framework\Workflow\Execution\Event\WorkflowTaskCreatedEvent;
use Shopwell\Core\Framework\Workflow\Execution\Graph\GraphNode;
use Shopwell\Core\Framework\Workflow\Execution\NodeOutcome;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowCountersignTypes;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowEventQueue;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionContext;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowNodeTypes;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowSignal;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceEntity;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskCollection;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskEntity;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskStateHandler;

/**
 * Resolve and snapshot assignees on entry; create sequential tasks one at a time and fail if no assignee resolves.
 *
 * @internal
 */
#[Package('framework')]
final class TaskExecutor implements NodeExecutorInterface
{
    /**
     * @param EntityRepository<WorkflowTaskCollection> $taskRepository
     */
    public function __construct(
        private readonly AssigneeResolverRegistry $resolvers,
        private readonly EntityRepository $taskRepository,
        private readonly WorkflowEventQueue $events,
        private readonly WorkflowTaskStateHandler $taskStates,
    ) {
    }

    public function getNodeType(): string
    {
        return WorkflowNodeTypes::TASK;
    }

    public function enter(WorkflowInstanceEntity $instance, GraphNode $node, WorkflowExecutionContext $context): NodeOutcome
    {
        $countersignType = WorkflowCountersignTypes::fromNode($node);
        $assigneeType = $node->property('assigneeType');

        if (!\is_string($assigneeType) || $assigneeType === '') {
            throw WorkflowExecutionException::unsupportedAssigneeType(get_debug_type($assigneeType));
        }

        $assignees = $this->resolveAssignees($instance, $node, $assigneeType, $context);

        if ($assignees === []) {
            throw WorkflowExecutionException::noAssignee($node->id, $assigneeType);
        }

        if ($countersignType === WorkflowCountersignTypes::ORDER_SIGN) {
            // Create the first task now; resume creates subsequent tasks from ext.assignees.
            $this->createTask($instance, $node, $assignees[0], $countersignType, $context, 1, $assignees);

            return NodeOutcome::wait(WorkflowSignal::TASK_COMPLETED);
        }

        $sequence = 1;

        foreach ($assignees as $assigneeId) {
            $this->createTask($instance, $node, $assigneeId, $countersignType, $context, $sequence++, null);
        }

        return NodeOutcome::wait(WorkflowSignal::TASK_COMPLETED);
    }

    public function resume(
        WorkflowInstanceEntity $instance,
        GraphNode $node,
        WorkflowSignal $signal,
        WorkflowExecutionContext $context,
    ): NodeOutcome {
        $taskId = $signal->payload['taskId'] ?? null;
        $result = $signal->payload['result'] ?? null;

        if ($signal->name !== WorkflowSignal::TASK_COMPLETED || !\is_string($taskId) || !\in_array($result, [CounterSignResult::AGREED, CounterSignResult::REJECTED, CounterSignResult::PENDING], true)) {
            throw WorkflowExecutionException::invalidSignal($signal->name);
        }

        $completedTask = $this->taskRepository->search(new Criteria([$taskId]), $context->context())->getEntities()->first()
            ?? throw WorkflowExecutionException::taskNotFound($taskId);

        if ($completedTask->instanceId !== $instance->id || $completedTask->nodeId !== $node->id || $completedTask->round !== $context->round()) {
            throw WorkflowExecutionException::taskAlreadyHandled($taskId);
        }

        if ($result === CounterSignResult::REJECTED) {
            return NodeOutcome::complete('rejected');
        }

        if ($completedTask->countersignType === WorkflowCountersignTypes::ORDER_SIGN && $result === CounterSignResult::PENDING) {
            $next = $this->nextAssignee($completedTask);

            if ($next !== null) {
                $this->createTask(
                    $instance,
                    $node,
                    $next,
                    WorkflowCountersignTypes::ORDER_SIGN,
                    $context,
                    $completedTask->sequence + 1,
                    $completedTask->assigneeSnapshot()
                );

                return NodeOutcome::wait(WorkflowSignal::TASK_COMPLETED);
            }

            // Fail when the assignee snapshot cannot identify the next person rather than silently skipping them.
            throw WorkflowExecutionException::noAssignee($node->id, $completedTask->assigneeType);
        }

        return $result === CounterSignResult::AGREED ? NodeOutcome::proceed() : NodeOutcome::wait(WorkflowSignal::TASK_COMPLETED);
    }

    /**
     * @return list<string>
     */
    private function resolveAssignees(
        WorkflowInstanceEntity $instance,
        GraphNode $node,
        string $assigneeType,
        WorkflowExecutionContext $context,
    ): array {
        $resolved = $this->resolvers->for($assigneeType)->resolve($instance, $node, $context);

        // Deduplicate assignees without changing their configured order.
        return array_values(array_unique(array_filter(
            $resolved,
            static fn (string $userId): bool => $userId !== ''
        )));
    }

    /**
     * Find the next assignee by snapshot position; sequence numbers may contain gaps after retries.
     */
    private function nextAssignee(WorkflowTaskEntity $completedTask): ?string
    {
        $snapshot = $completedTask->assigneeSnapshot();
        $index = array_search($completedTask->assigneeId, $snapshot, true);

        if ($index === false) {
            return null;
        }

        return $snapshot[$index + 1] ?? null;
    }

    /**
     * @param list<string>|null $assigneeSnapshot
     */
    private function createTask(
        WorkflowInstanceEntity $instance,
        GraphNode $node,
        string $assigneeId,
        string $countersignType,
        WorkflowExecutionContext $context,
        int $sequence,
        ?array $assigneeSnapshot,
    ): void {
        $taskId = Uuid::randomHex();

        $payload = [
            'id' => $taskId,
            'instanceId' => $instance->id,
            'nodeId' => $node->id,
            'nodeName' => $node->name(),
            'countersignType' => $countersignType,
            'round' => $context->round(),
            'sequence' => $sequence,
            'assigneeId' => $assigneeId,
            'assigneeType' => (string) $node->property('assigneeType', ''),
            'stateId' => $this->taskStates->initialStateId($context->context()),
            'ext' => ['nodeExecutionId' => $context->executionId()],
        ];

        if ($assigneeSnapshot !== null) {
            $payload['ext'][WorkflowTaskEntity::EXT_ASSIGNEES] = $assigneeSnapshot;
        }

        $this->taskRepository->create([$payload], $context->context());

        // Notify on task creation so each sequential assignee receives a notification when their turn starts.
        $this->events->push(new WorkflowTaskCreatedEvent(
            $taskId,
            $instance->id,
            $node->id,
            $node->name(),
            $assigneeId,
            $countersignType
        ));
    }
}
