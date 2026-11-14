<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Instance;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Node\Approval\ApprovalWorkflowService;

/**
 * @internal
 *
 * 实例状态机。
 *
 * 状态：running → agreed / rejected / canceled，三条动作都只从 running 出发，
 * 也就是**终态不可再变**。这条约束是"同一实例被重复审批"的第二道闸。
 *
 * 状态只能经 {@see WorkflowInstanceStateHandler} 改，引擎里不许直接写 `stateId`。
 */
#[Package('framework')]
final class WorkflowInstanceStates
{
    public const STATE_MACHINE = 'workflow_instance.state';

    /**
     * 运行中
     */
    public const RUNNING = 'running';
    public const COMPLETED = 'completed';
    public const ACTION_COMPLETE = 'complete';

    /**
     * 已通过（走到结束节点，且结束节点由同意路径抵达）
     */
    public const AGREED = 'agreed';

    /**
     * 已拒绝（任一审批人拒绝，或结束节点由拒绝路径抵达）
     */
    public const REJECTED = 'rejected';

    /**
     * 已撤销（由发起人撤回，见 ApprovalWorkflowService::cancel）
     */
    public const CANCELED = 'canceled';

    public const ACTION_AGREE = 'agree';
    public const ACTION_REJECT = 'reject';
    public const ACTION_CANCEL = 'cancel';

    /**
     * 状态 → 契约里的 status 字符串。
     *
     * 前端契约用的是大写（RUNNING/AGREE/REJECT/CANCEL），与数据库里的
     * 小写状态名刻意不同：契约一改前端就编译不过，能挡住"顺手把库里的值改了"。
     */
    public const CONTRACT_STATUS = [
        self::RUNNING => 'RUNNING',
        self::COMPLETED => 'COMPLETED',
        self::AGREED => 'AGREE',
        self::REJECTED => 'REJECT',
        self::CANCELED => 'CANCEL',
    ];
}
