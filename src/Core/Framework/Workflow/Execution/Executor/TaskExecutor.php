<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Executor;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Framework\Workflow\Execution\Assignee\AssigneeResolverRegistry;
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
 * @internal
 *
 * 审批节点：解析审批人、建任务、顺序会签逐条推进。
 *
 * ## 为什么审批人在 `enter` 里解析一次就快照
 *
 * 顺序会签要"轮到谁建谁"。如果每次推进都重新解析审批人，期间组织架构一变
 * （有人调部门、角色成员被改），就会出现漏人、多出人、或者同一个人审两次。
 * 所以进入节点时解析一次，把**完整名单**写进第一条任务的 `ext.assignees`，
 * 后续只读这份快照。
 *
 * ## 为什么顺序会签只建第一条任务
 *
 * 一次建完的话，3 个审批人会同时在待办列表里看到单子，谁都能先审——
 * 那就不是"顺序"会签了。正确做法是：只建当前这个人的，他同意后由
 * {@see resume()} 建下一条。
 *
 * ## 审批人解析为空必须抛异常
 *
 * 例如"部门负责人"节点但发起人所在部门没设负责人。此时如果静默跳过，
 * 流程会继续往下走，而界面上看不出少了一审——这是审批系统最严重的一类 bug。
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
            // 只建第一条，其余等 resume。快照写在任务行的 ext.assignees 上
            $this->createTask($instance, $node, $assignees[0], $countersignType, $context, 1, $assignees);

            return NodeOutcome::wait('core:task-completed');
        }

        $sequence = 1;

        foreach ($assignees as $assigneeId) {
            $this->createTask($instance, $node, $assigneeId, $countersignType, $context, $sequence++, null);
        }

        return NodeOutcome::wait('core:task-completed');
    }

    public function resume(
        WorkflowInstanceEntity $instance,
        GraphNode $node,
        WorkflowSignal $signal,
        WorkflowExecutionContext $context,
    ): NodeOutcome {
        $taskId = $signal->payload['taskId'] ?? null;
        $result = $signal->payload['result'] ?? null;

        if ($signal->name !== 'core:task-completed' || !\is_string($taskId) || !\in_array($result, ['agreed', 'rejected', 'pending'], true)) {
            throw WorkflowExecutionException::invalidSignal($signal->name);
        }

        $completedTask = $this->taskRepository->search(new Criteria([$taskId]), $context->context())->getEntities()->first()
            ?? throw WorkflowExecutionException::taskNotFound($taskId);

        if ($completedTask->instanceId !== $instance->id || $completedTask->nodeId !== $node->id || $completedTask->round !== $context->round()) {
            throw WorkflowExecutionException::taskAlreadyHandled($taskId);
        }

        if ($result === 'rejected') {
            return NodeOutcome::complete('rejected');
        }

        if ($completedTask->countersignType === WorkflowCountersignTypes::ORDER_SIGN && $result === 'pending') {
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

                return NodeOutcome::wait('core:task-completed');
            }

            // 快照里还有"没轮到的人"，但按当前这条推算不出下一位：
            // 说明快照被改过，或审批人重复了。宁可报错，也不要跳过后面的人。
            throw WorkflowExecutionException::noAssignee($node->id, $completedTask->assigneeType);
        }

        return $result === 'agreed' ? NodeOutcome::proceed() : NodeOutcome::wait('core:task-completed');
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

        // 去重且保持原顺序：会签里同一个人出现两次会变成两条任务，
        // 分母变 2、他一个人点两次就通过，等于把会签变成了单人审批。
        // array_unique 保留首次出现的位置，正好是我们要的语义。
        return array_values(array_unique(array_filter(
            $resolved,
            static fn (string $userId): bool => $userId !== ''
        )));
    }

    /**
     * 顺序会签的下一位：快照里排在当前审批人后面的那个。
     *
     * 用"当前人在快照里的位置 + 1"而不是 `sequence` 索引：
     * `sequence` 是建任务的序号，历史数据里可能因为重试出现跳号，
     * 而快照是进入节点时的原始顺序，永远稳定。
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
     * @param list<string>|null $assigneeSnapshot 仅顺序会签传，其余为 null
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

        // 通知挂在"任务创建"上而不是"进入节点"：会签要按人发，
        // 顺序会签的后续任务是逐条建的，只有这样每个轮到的人才会收到通知
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
