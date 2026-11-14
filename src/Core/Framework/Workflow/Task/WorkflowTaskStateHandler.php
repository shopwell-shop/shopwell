<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Task;

use Psr\Clock\ClockInterface;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;
use Shopwell\Core\System\StateMachine\StateMachineRegistry;
use Shopwell\Core\System\StateMachine\Transition;

/**
 * @internal
 *
 * 任务状态的唯一出口。
 *
 * 除了状态机，还要写 `completedAt`：待办列表按它排序，审批效率统计按它算时长。
 * 两个写入放在同一个方法里，避免出现"已同意但没有完成时间"的行。
 *
 * **状态机是并发第一道闸**：三条迁移都只从 `todo` 出发，
 * 同一条任务第二次同意会抛非法迁移异常。不要 try/catch 掉它换成"返回 false"，
 * 那会让重复提交看起来成功，前端提示不一致。
 */
#[Package('framework')]
final class WorkflowTaskStateHandler
{
    /**
     * @param EntityRepository<WorkflowTaskCollection> $taskRepository
     */
    public function __construct(
        private readonly StateMachineRegistry $stateMachineRegistry,
        private readonly EntityRepository $taskRepository,
        private readonly ClockInterface $clock,
    ) {
    }

    public function initialStateId(Context $context): string
    {
        return $this->stateMachineRegistry->getStateMachine(WorkflowTaskStates::STATE_MACHINE, $context)->getInitialStateId()
            ?? throw WorkflowExecutionException::invalidGraph('The approval state machine has no initial state.');
    }

    public function agree(string $taskId, Context $context): void
    {
        $this->transition($taskId, WorkflowTaskStates::ACTION_AGREE, $context);
    }

    public function reject(string $taskId, Context $context): void
    {
        $this->transition($taskId, WorkflowTaskStates::ACTION_REJECT, $context);
    }

    /**
     * 取消任务：实例被撤销、节点被跳过、加签被撤回时用。
     * 取消后的任务**不计入会签分母**，见 {@see WorkflowTaskStates::COUNTED_STATES}。
     */
    public function cancel(string $taskId, Context $context): void
    {
        $this->transition($taskId, WorkflowTaskStates::ACTION_CANCEL, $context);
    }

    private function transition(string $taskId, string $action, Context $context): void
    {
        $this->stateMachineRegistry->transition(
            new Transition(WorkflowTaskEntity::ENTITY_NAME, $taskId, $action, 'stateId'),
            $context
        );

        $this->taskRepository->update([
            [
                'id' => $taskId,
                'completedAt' => $this->clock->now(),
            ],
        ], $context);
    }
}
