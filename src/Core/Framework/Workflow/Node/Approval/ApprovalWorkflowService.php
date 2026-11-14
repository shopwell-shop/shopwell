<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Node\Approval;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\Copy\WorkflowCopyCollection;
use Shopwell\Core\Framework\Workflow\Execution\Definition\WorkflowExecutionDefinitionEntity;
use Shopwell\Core\Framework\Workflow\Execution\Graph\GraphData;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowCancellationStore;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowEventQueue;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionContext;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowService;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowTransactionRunner;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceEntity;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceStateHandler;
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
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createDefinition(array $data, Context $context): string
    {
        return $this->workflows->createDefinition($data, $context);
    }

    /**
     * @param array<string, mixed> $graphData
     * @param list<array<string, mixed>>|null $formSchema
     */
    public function saveDefinition(string $definitionId, array $graphData, ?array $formSchema, Context $context): void
    {
        $this->workflows->saveDefinition($definitionId, $graphData, $formSchema, $context);
    }

    public function publishDefinition(string $definitionId, Context $context): void
    {
        $this->workflows->publishDefinition($definitionId, $context);
    }

    /**
     * @param array<string, mixed> $formData
     */
    public function start(string $definitionId, array $formData, string $title, Context $context): string
    {
        return $this->workflows->start($definitionId, $formData, $title, $context);
    }

    public function loadDefinition(string $id, Context $context): WorkflowExecutionDefinitionEntity
    {
        return $this->workflows->loadDefinition($id, $context);
    }

    public function loadInstance(string $id, Context $context): WorkflowInstanceEntity
    {
        return $this->workflows->loadInstance($id, $context);
    }

    public function actorId(Context $context): string
    {
        return $this->workflows->actorId($context);
    }

    /**
     * @return array{nodes: list<array<string, mixed>>, edges: list<array<string, mixed>>}
     */
    public static function initialGraph(): array
    {
        return WorkflowService::initialGraph();
    }

    public function approve(string $taskId, ?string $comment, Context $context): void
    {
        $this->handleTask($taskId, true, $comment, $context);
    }

    public function reject(string $taskId, ?string $comment, Context $context): void
    {
        if ($comment === null || trim($comment) === '') {
            throw WorkflowExecutionException::commentRequired();
        }

        $this->handleTask($taskId, false, $comment, $context);
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
        $instance = $this->loadInstance($instanceId, $context);

        if ($viewerId !== null && !$this->canView($instance, $viewerId, $context)) {
            throw WorkflowExecutionException::forbidden('不是这张单据的发起人、审批人或抄送人');
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
        $actorId = $this->actorId($context);
        $context->scope(Context::SYSTEM_SCOPE, function (Context $scoped) use ($instanceId, $actorId, $reason): void {
            $this->transactions->run(function () use ($instanceId, $actorId, $reason, $scoped): void {
                $instance = $this->cancellations->lockInstance($instanceId);

                if ($instance['initiatorId'] !== $actorId) {
                    throw WorkflowExecutionException::forbidden('只有发起人可以撤销');
                }

                if ($instance['stateId'] !== $this->instanceStates->initialStateId($scoped)) {
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

    private function handleTask(string $taskId, bool $agreed, ?string $comment, Context $context): void
    {
        $actorId = $this->actorId($context);

        // Resolve ownership before opening the transaction: an ordinary read inside
        // REPEATABLE READ would pin a stale snapshot before the instance lock is acquired.
        $task = $this->loadTask($taskId, $context);

        if ($task->assigneeId !== $actorId) {
            throw WorkflowExecutionException::taskNotYours($taskId);
        }

        $this->transactions->run(function () use ($task, $taskId, $agreed, $comment, $context, $actorId): void {
            $context->scope(Context::SYSTEM_SCOPE, function (Context $scoped) use ($task, $taskId, $agreed, $comment, $actorId): void {
                // 锁实例：同一张单的审批请求排队，避免两个会签人同时算出 pending
                $this->transactions->lockInstance($task->instanceId);

                // 锁之后重读：锁等待期间任务可能已被处理，或实例已被撤销
                $fresh = $this->loadTask($taskId, $scoped);

                if ($fresh->assigneeId !== $actorId) {
                    throw WorkflowExecutionException::taskNotYours($taskId);
                }

                if (!$fresh->isTodo()) {
                    throw WorkflowExecutionException::taskAlreadyHandled($taskId);
                }

                $instance = $this->loadInstance($fresh->instanceId, $scoped);

                if (!$instance->isRunning()) {
                    throw WorkflowExecutionException::instanceNotRunning($fresh->instanceId);
                }

                if ($fresh->nodeId !== $instance->currentNodeId || $fresh->round !== $instance->round) {
                    throw WorkflowExecutionException::taskAlreadyHandled($taskId);
                }

                $graph = GraphData::fromArray($instance->snapshot());
                $execution = new WorkflowExecutionContext($graph, $instance, $scoped, $actorId, $instance->round);

                $this->approvalCompletion->complete($fresh, $agreed, $comment, $execution);
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

        // 抄送人也能看：抄送的意义就是"通知你看一眼"
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('instanceId', $instance->id));
        $criteria->addFilter(new EqualsFilter('userId', $viewerId));
        $criteria->setLimit(1);

        return $this->copyRepository->search($criteria, $context)->getTotal() > 0;
    }
}
