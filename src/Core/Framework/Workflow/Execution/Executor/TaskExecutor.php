<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Executor;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Framework\Workflow\Execution\Assignee\ApprovalAssigneeResolver;
use Shopwell\Core\Framework\Workflow\Execution\CounterSignCalculator;
use Shopwell\Core\Framework\Workflow\Execution\CounterSignResult;
use Shopwell\Core\Framework\Workflow\Execution\Event\WorkflowTaskCompletedEvent;
use Shopwell\Core\Framework\Workflow\Execution\Event\WorkflowTaskCreatedEvent;
use Shopwell\Core\Framework\Workflow\Execution\Graph\GraphNode;
use Shopwell\Core\Framework\Workflow\Execution\Log\WorkflowRecorder;
use Shopwell\Core\Framework\Workflow\Execution\NodeOutcome;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowEventQueue;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionContext;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowNodeTypes;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowSignal;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceEntity;
use Shopwell\Core\Framework\Workflow\Node\Approval\Automation\ApprovalAutomation;
use Shopwell\Core\Framework\Workflow\Node\Approval\Automation\AutomaticApprovalResolver;
use Shopwell\Core\Framework\Workflow\Node\Approval\Mode\ApprovalModeRegistry;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskCollection;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskEntity;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskFinder;
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
        private readonly ApprovalAssigneeResolver $assignees,
        private readonly EntityRepository $taskRepository,
        private readonly WorkflowEventQueue $events,
        private readonly WorkflowTaskStateHandler $taskStates,
        private readonly ApprovalModeRegistry $modes,
        private readonly AutomaticApprovalResolver $automatic,
        private readonly WorkflowTaskFinder $tasks,
        private readonly CounterSignCalculator $calculator,
        private readonly WorkflowRecorder $recorder,
    ) {
    }

    public function getNodeType(): string
    {
        return WorkflowNodeTypes::TASK;
    }

    public function enter(WorkflowInstanceEntity $instance, GraphNode $node, WorkflowExecutionContext $context): NodeOutcome
    {
        $mode = $this->modes->forNode($node);
        $countersignType = $mode->getType();
        if ($node->property('handlingTask') === true) {
            $assignment = $this->assignees->resolve($instance, $node, $context);
            if ($assignment->assigneeIds === []) {
                throw WorkflowExecutionException::noAssignee($node->id, (string) $node->property('assigneeType', ''));
            }
            $task = $this->createTask($instance, $node, $assignment->assigneeIds[0], $countersignType, $context, 1, $assignment->assigneeIds, []);
            $this->notify($task);

            return NodeOutcome::wait(WorkflowSignal::TASK_COMPLETED);
        }
        $policy = ApprovalAutomation::fromConfiguration(\array_key_exists('approvalAutomation', $node->properties) ? $node->properties['approvalAutomation'] : []);
        if ($policy->decision !== 'manual') {
            $output = ['decision' => $policy->decision, 'reason' => 'configured'];

            return $policy->decision === 'approve' ? NodeOutcome::proceed(output: $output) : NodeOutcome::complete('rejected', $output);
        }
        $assignment = $this->assignees->resolve($instance, $node, $context);
        if ($assignment->automaticDecision !== null) {
            $output = ['decision' => $assignment->automaticDecision, 'reason' => 'missing-assignee'];

            return $assignment->automaticDecision === 'approve'
                ? NodeOutcome::proceed(output: $output)
                : NodeOutcome::complete('rejected', $output);
        }
        $assignees = $assignment->assigneeIds;

        $automatic = $this->automatic->resolve($instance, $node, $assignees, $context->context());
        if ($mode->isSequential()) {
            return $this->enterSequential($instance, $node, $context, $assignees, $automatic, 1);
        }

        foreach ($assignees as $index => $assigneeId) {
            $this->createTask($instance, $node, $assigneeId, $countersignType, $context, $index + 1, null, $automatic);
        }

        $tasks = $this->tasks->forNode($instance->id, $node->id, $context->round(), $context->context());
        $result = $this->calculator->resolve($tasks, $countersignType, configuration: $node->property('approvalModeConfig', []), version: $mode->getVersion());
        foreach ($tasks as $task) {
            if (!$task->isTodo()) {
                continue;
            }
            if ($result->isPending()) {
                $this->notify($task);
            } else {
                $this->taskStates->cancel($task->id, $context->context());
            }
        }

        if ($result->isAgreed()) {
            return NodeOutcome::proceed();
        }
        if ($result->isRejected()) {
            return NodeOutcome::complete('rejected');
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

        $mode = $this->modes->forNode($node);

        if ($mode->isSequential() && $result === CounterSignResult::PENDING) {
            $snapshot = $completedTask->assigneeSnapshot();
            if (!isset($snapshot[$completedTask->sequence])) {
                throw WorkflowExecutionException::noAssignee($node->id, $completedTask->assigneeType);
            }
            $automatic = $completedTask->ext['automaticApprovals'] ?? [];
            if (!\is_array($automatic) || array_filter($automatic, static fn (mixed $reason): bool => !\in_array($reason, ['initiator', 'previous-approval'], true)) !== []) {
                throw WorkflowExecutionException::invalidGraph('Invalid automatic approval snapshot.');
            }

            return $this->enterSequential($instance, $node, $context, $snapshot, $automatic, $completedTask->sequence + 1);
        }

        return $result === CounterSignResult::AGREED ? NodeOutcome::proceed() : NodeOutcome::wait(WorkflowSignal::TASK_COMPLETED);
    }

    /**
     * @param list<string> $assignees
     * @param array<string, string> $automatic
     */
    private function enterSequential(WorkflowInstanceEntity $instance, GraphNode $node, WorkflowExecutionContext $context, array $assignees, array $automatic, int $sequence): NodeOutcome
    {
        $mode = $this->modes->forNode($node);
        for ($index = $sequence - 1; $index < \count($assignees); ++$index) {
            $task = $this->createTask($instance, $node, $assignees[$index], $mode->getType(), $context, $index + 1, $assignees, $automatic);
            if (!isset($automatic[$assignees[$index]])) {
                $this->notify($task);

                return NodeOutcome::wait(WorkflowSignal::TASK_COMPLETED);
            }
        }

        $tasks = $this->tasks->forNode($instance->id, $node->id, $context->round(), $context->context());
        $result = $this->calculator->resolve($tasks, $mode->getType(), \count($assignees), $node->property('approvalModeConfig', []), $mode->getVersion());
        if ($result->isRejected()) {
            return NodeOutcome::complete('rejected');
        }
        if (!$result->isAgreed()) {
            throw WorkflowExecutionException::invalidGraph('The approval mode did not complete after all sequential reviewers agreed.');
        }

        return NodeOutcome::proceed();
    }

    /**
     * @param list<string>|null $assigneeSnapshot
     * @param array<string, string> $automatic
     */
    private function createTask(
        WorkflowInstanceEntity $instance,
        GraphNode $node,
        string $assigneeId,
        string $countersignType,
        WorkflowExecutionContext $context,
        int $sequence,
        ?array $assigneeSnapshot,
        array $automatic,
    ): WorkflowTaskEntity {
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
            $payload['ext']['automaticApprovals'] = $automatic;
        }

        $this->taskRepository->create([$payload], $context->context());

        $task = $this->taskRepository->search(new Criteria([$taskId]), $context->context())->getEntities()->first()
            ?? throw WorkflowExecutionException::taskNotFound($taskId);
        if (isset($automatic[$assigneeId])) {
            $this->taskStates->agree($taskId, $context->context());
            $this->recorder->recordTaskAction($task, 'agree', null, ['automatic' => true, 'reason' => $automatic[$assigneeId]], $context->context());
            $this->events->push(new WorkflowTaskCompletedEvent($instance->id, $taskId, $node->id, $assigneeId, true, null));
        }

        return $task;
    }

    private function notify(WorkflowTaskEntity $task): void
    {
        $this->events->push(new WorkflowTaskCreatedEvent(
            $task->id,
            $task->instanceId,
            $task->nodeId,
            $task->nodeName,
            $task->assigneeId,
            $task->countersignType,
        ));
    }
}
