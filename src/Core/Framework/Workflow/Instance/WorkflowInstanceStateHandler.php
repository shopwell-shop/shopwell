<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Instance;

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
 * 实例状态的唯一出口。
 *
 * 全项目只有这里能写 `workflow_instance.state_id`：引擎、服务、控制器、监听器
 * 一律调本类方法。理由不是洁癖——状态机迁移会顺手写 `state_machine_history`，
 * 绕过它直接改列，审计链就断了，而且"终态不可再变"这条约束也失效。
 *
 * `endedAt` 与终态在同一个方法里写：两处分开写会出现"已结束但没有结束时间"
 * 的行，报表按 endedAt 统计耗时时会漏掉这些单。
 */
#[Package('framework')]
final class WorkflowInstanceStateHandler
{
    /**
     * @param EntityRepository<WorkflowInstanceCollection> $instanceRepository
     */
    public function __construct(
        private readonly StateMachineRegistry $stateMachineRegistry,
        private readonly EntityRepository $instanceRepository,
        private readonly ClockInterface $clock,
    ) {
    }

    public function initialStateId(Context $context): string
    {
        return $this->stateMachineRegistry->getStateMachine(WorkflowInstanceStates::STATE_MACHINE, $context)->getInitialStateId()
            ?? throw WorkflowExecutionException::invalidGraph('The workflow instance state machine has no initial state.');
    }

    public function complete(string $instanceId, string $result, Context $context): void
    {
        $this->transition($instanceId, WorkflowInstanceStates::ACTION_COMPLETE, $context);
        $this->instanceRepository->update([['id' => $instanceId, 'result' => $result]], $context);
    }

    public function agree(string $instanceId, Context $context): void
    {
        $this->transition($instanceId, WorkflowInstanceStates::ACTION_AGREE, $context);
    }

    public function reject(string $instanceId, Context $context): void
    {
        $this->transition($instanceId, WorkflowInstanceStates::ACTION_REJECT, $context);
    }

    public function cancel(string $instanceId, Context $context): void
    {
        $this->transition($instanceId, WorkflowInstanceStates::ACTION_CANCEL, $context);
    }

    /**
     * 只允许从 running 出发；重复同意会撞非法迁移并抛 StateMachineException。
     * 这个异常**不要**在业务代码里吞掉——它就是并发闸门在工作。
     */
    private function transition(string $instanceId, string $action, Context $context): void
    {
        // 状态机内部会切 system scope，与调用方当前 scope 无关
        $this->stateMachineRegistry->transition(
            new Transition(WorkflowInstanceEntity::ENTITY_NAME, $instanceId, $action, 'stateId'),
            $context
        );

        $this->instanceRepository->update([
            [
                'id' => $instanceId,
                'endedAt' => $this->clock->now(),
            ],
        ], $context);
    }
}
