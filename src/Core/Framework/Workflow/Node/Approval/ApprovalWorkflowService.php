<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Node\Approval;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\Copy\WorkflowCopyCollection;
use Shopwell\Core\Framework\Workflow\Execution\Graph\GraphData;
use Shopwell\Core\Framework\Workflow\Execution\Log\WorkflowRecorder;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowCancellationStore;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowEventQueue;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionContext;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowService;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowTransactionRunner;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceEntity;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceStateHandler;
use Shopwell\Core\Framework\Workflow\Node\Approval\Extension\ApprovalTaskOperationExtension;
use Shopwell\Core\Framework\Workflow\Node\Approval\Form\ApprovalFormService;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskCollection;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskEntity;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskFinder;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskHistoryCollection;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskStateHandler;

/**
 * @internal Approval operations compose the generic workflow lifecycle.
 */
#[Package('framework')]
final readonly class ApprovalWorkflowService
{
    /**
     * @param EntityRepository<WorkflowTaskCollection> $taskRepository
     * @param EntityRepository<WorkflowCopyCollection> $copyRepository
     */
    public function __construct(
        private WorkflowService $workflows,
        private EntityRepository $taskRepository,
        private EntityRepository $copyRepository,
        private WorkflowTransactionRunner $transactions,
        private WorkflowEventQueue $events,
        private WorkflowTaskFinder $finder,
        private WorkflowTaskStateHandler $taskStates,
        private WorkflowInstanceStateHandler $instanceStates,
        private WorkflowCancellationStore $cancellations,
        private ApprovalTaskCompletion $approvalCompletion,
        private ApprovalTaskParticipation $participation,
        private ApprovalResubmission $resubmission,
        private ApprovalTaskPolicy $operations,
        private ExtensionDispatcher $extensions,
        private WorkflowRecorder $recorder,
        private ApprovalFormService $forms,
    ) {
    }

    /**
     * @param array<string, mixed> $formData
     */
    public function approve(string $taskId, ?string $comment, Context $context, array $formData = [], ?int $formVersion = null): void
    {
        $this->handleTask($taskId, $context, function (WorkflowTaskEntity $task, WorkflowExecutionContext $execution) use ($comment, $formData, $formVersion): void {
            $execution = $this->forms->approve($task, $execution, $formData, $formVersion);
            $this->approvalCompletion->complete($task, true, $comment, $execution);
        });
    }

    public function reject(string $taskId, ?string $comment, Context $context): void
    {
        if ($comment === null || trim($comment) === '') {
            throw WorkflowExecutionException::commentRequired();
        }

        $this->handleTask($taskId, $context, fn (WorkflowTaskEntity $task, WorkflowExecutionContext $execution) => $this->approvalCompletion->complete($task, false, $comment, $execution));
    }

    public function returnToInitiator(string $taskId, string $comment, Context $context): void
    {
        if (trim($comment) === '') {
            throw WorkflowExecutionException::commentRequired();
        }

        $this->handleTask(
            $taskId,
            $context,
            fn (WorkflowTaskEntity $task, WorkflowExecutionContext $execution) => $this->resubmission->returnToInitiator($task, trim($comment), $execution),
            ApprovalTaskActions::RETURN,
        );
    }

    public function participate(string $taskId, string $action, string $targetUserId, string $comment, Context $context): void
    {
        if (trim($comment) === '') {
            throw WorkflowExecutionException::commentRequired();
        }

        $this->handleTask(
            $taskId,
            $context,
            fn (WorkflowTaskEntity $task, WorkflowExecutionContext $execution) => $this->participation->apply($task, $action, $targetUserId, trim($comment), $execution),
            $action,
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function executeOperation(string $taskId, string $action, array $payload, string $comment, Context $context): void
    {
        if (trim($comment) === '') {
            throw WorkflowExecutionException::commentRequired();
        }

        if (\in_array($action, ApprovalTaskActions::OPERATIONS, true)) {
            throw WorkflowExecutionException::forbidden('Use the dedicated endpoint for built-in approval operations.');
        }

        $this->handleTask($taskId, $context, function (WorkflowTaskEntity $task, WorkflowExecutionContext $execution) use ($action, $payload, $comment): void {
            $handled = $this->extensions->publish(
                ApprovalTaskOperationExtension::NAME,
                new ApprovalTaskOperationExtension($task, $action, $payload, trim($comment), $execution),
                static fn (mixed ...$parameters): bool => throw WorkflowExecutionException::forbidden('The approval operation has no installed handler.'),
            );

            if ($handled !== true) {
                throw WorkflowExecutionException::forbidden('The approval operation did not complete.');
            }

            $this->recorder->recordTaskAction($task, ApprovalTaskActions::CUSTOM, trim($comment), ['operation' => $action], $execution->context());
        }, $action);
    }

    /**
     * @return EntitySearchResult<WorkflowTaskCollection>
     */
    public function todo(string $userId, Criteria $criteria, Context $context): EntitySearchResult
    {
        return $this->finder->todoOf($userId, $criteria, $context);
    }

    /**
     * @return EntitySearchResult<WorkflowTaskHistoryCollection>
     */
    public function done(string $userId, Criteria $criteria, Context $context): EntitySearchResult
    {
        return $this->finder->doneOf($userId, $criteria, $context);
    }

    public function instanceDetail(string $instanceId, Context $context, ?string $viewerId = null): WorkflowInstanceEntity
    {
        $instance = $this->workflows->loadInstance($instanceId, $context);

        if ($viewerId !== null && !$this->canView($instance, $viewerId, $context)) {
            throw WorkflowExecutionException::forbidden('The current user is not the initiator, an assignee or a copy recipient.');
        }

        return $instance;
    }

    public function loadTask(string $taskId, Context $context): WorkflowTaskEntity
    {
        /** @var WorkflowTaskEntity|null $task */
        $task = $this->taskRepository
            ->search((new Criteria([$taskId]))->addAssociation('stateMachineState'), $context)
            ->getEntities()
            ->first();

        return $task ?? throw WorkflowExecutionException::taskNotFound($taskId);
    }

    public function cancel(string $instanceId, string $reason, Context $context): void
    {
        $actorId = $this->workflows->actorId($context);
        $context->scope(Context::SYSTEM_SCOPE, function (Context $scoped) use ($instanceId, $actorId, $reason): void {
            $this->transactions->run(function () use ($instanceId, $actorId, $reason, $scoped): void {
                $instance = $this->cancellations->lockInstance($instanceId);

                if ($instance['initiatorId'] !== $actorId) {
                    throw WorkflowExecutionException::forbidden('Only the initiator can cancel this instance.');
                }

                if (!\in_array($instance['stateId'], [$this->instanceStates->initialStateId($scoped), $this->instanceStates->returnedStateId($scoped)], true)) {
                    throw WorkflowExecutionException::instanceNotRunning($instanceId);
                }

                $taskIds = $this->cancellations->lockPendingTasks($instanceId, $this->taskStates->initialStateId($scoped));
                $this->cancellations->beginCancellation($instanceId, $taskIds);

                foreach ($taskIds as $taskId) {
                    $this->taskStates->cancel($taskId, $scoped);
                }

                $this->workflows->cancel($instanceId, $reason, $scoped);
            });
        });

        $this->events->flush();
    }

    private function handleTask(string $taskId, Context $context, \Closure $operation, ?string $action = null): void
    {
        $actorId = $this->workflows->actorId($context);

        // Resolve ownership before opening the transaction: an ordinary read inside
        // REPEATABLE READ would pin a stale snapshot before the instance lock is acquired.
        $task = $this->loadTask($taskId, $context);

        if ($task->assigneeId !== $actorId) {
            throw WorkflowExecutionException::taskNotYours($taskId);
        }

        $this->transactions->run(function () use ($task, $taskId, $operation, $context, $actorId, $action): void {
            $context->scope(Context::SYSTEM_SCOPE, function (Context $scoped) use ($task, $taskId, $operation, $actorId, $action): void {
                // Serialize decisions by locking the instance before aggregating sibling tasks.
                $this->transactions->lockInstance($task->instanceId);

                // Reload after acquiring the lock because a task may have completed or the instance may have been canceled while waiting.
                $fresh = $this->loadTask($taskId, $scoped);

                if ($fresh->assigneeId !== $actorId) {
                    throw WorkflowExecutionException::taskNotYours($taskId);
                }

                if (!$fresh->isTodo()) {
                    throw WorkflowExecutionException::taskAlreadyHandled($taskId);
                }

                $instance = $this->workflows->loadInstance($fresh->instanceId, $scoped);

                if (!$instance->isRunning()) {
                    throw WorkflowExecutionException::instanceNotRunning($fresh->instanceId);
                }

                if ($fresh->nodeId !== $instance->currentNodeId || $fresh->round !== $instance->round) {
                    throw WorkflowExecutionException::taskAlreadyHandled($taskId);
                }

                $graph = GraphData::fromArray($instance->snapshot());
                $execution = new WorkflowExecutionContext($graph, $instance, $scoped, $actorId, $instance->round);

                if ($action !== null) {
                    $this->operations->assertAllowed($action, $fresh, $instance, $scoped);
                }

                $operation($fresh, $execution);
            });
        });

        $this->events->flush();
    }

    private function canView(WorkflowInstanceEntity $instance, string $viewerId, Context $context): bool
    {
        if ($instance->initiatorId === $viewerId) {
            return true;
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('instanceId', $instance->id));
        $criteria->addFilter(new EqualsFilter('assigneeId', $viewerId));
        $criteria->setLimit(1);

        if ($this->taskRepository->search($criteria, $context)->getTotal() > 0) {
            return true;
        }

        // Copy recipients may also view the instance.
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('instanceId', $instance->id));
        $criteria->addFilter(new EqualsFilter('userId', $viewerId));
        $criteria->setLimit(1);

        return $this->copyRepository->search($criteria, $context)->getTotal() > 0;
    }
}
