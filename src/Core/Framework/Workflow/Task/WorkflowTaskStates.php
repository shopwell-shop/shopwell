<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Task;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\CounterSignCalculator;

/**
 * @internal
 *
 * 任务状态机。
 *
 * 状态：todo → agreed / rejected / canceled。
 * 三条动作都只从 todo 出发，于是"同一条任务提交两次"会撞非法迁移而抛异常——
 * 这是并发审批的第一道闸（第二道是聚合前对实例行 FOR UPDATE）。
 *
 * 状态只能经 {@see WorkflowTaskStateHandler} 改。
 */
#[Package('framework')]
final class WorkflowTaskStates
{
    public const STATE_MACHINE = 'workflow_task.state';

    /**
     * 待办
     */
    public const TODO = 'todo';

    /**
     * 已同意
     */
    public const AGREED = 'agreed';

    /**
     * 已拒绝
     */
    public const REJECTED = 'rejected';

    /**
     * 已取消（节点被跳过、实例被撤销、加签被撤回时置此状态）
     */
    public const CANCELED = 'canceled';

    public const ACTION_AGREE = 'agree';
    public const ACTION_REJECT = 'reject';
    public const ACTION_CANCEL = 'cancel';

    /**
     * 仍占用会签分母的状态。
     *
     * **取消的任务不计入分母**：加签被撤回、或签中有人被移除时任务置 canceled，
     * 若还把它算进分母，会签永远凑不齐"全部同意"，流程永久卡死。
     * 这个常量由 {@see CounterSignCalculator} 使用，别在别处另写一份数组。
     */
    public const COUNTED_STATES = [self::TODO, self::AGREED, self::REJECTED];

    /**
     * 终态：不再产生动作
     */
    public const FINAL_STATES = [self::AGREED, self::REJECTED, self::CANCELED];

    /**
     * 预留（当前**不注册**进状态机，扩展时加状态 + 迁移即可）：
     * FUTURE_SKIPPED（加签被跳过）、FUTURE_TRANSFERRED（已转办）、FUTURE_DELEGATED（代理完成）。
     * 注意：加这些状态时把 COUNTED_STATES 一并复核，否则会签分母会算错。
     */
    public const FUTURE_SKIPPED = 'skipped';
    public const FUTURE_TRANSFERRED = 'transferred';
    public const FUTURE_DELEGATED = 'delegated';
}
