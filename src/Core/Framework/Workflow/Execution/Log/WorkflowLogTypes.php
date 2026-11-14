<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Log;

use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 *
 * 日志类型（`workflow_log.log_type`）。
 *
 * `action` 说"做了什么"（agree/reject/copy/...），`log_type` 说"属于哪类操作"。
 * 分开是为了将来把退回、加签、转办的记录筛出来时不用改 action 词表——
 * 退回会复用 `reject` 这个 action，但 `log_type` 是 `return`，两者语义不同：
 * 前者结束流程，后者回到上一步继续跑。
 */
#[Package('framework')]
final class WorkflowLogTypes
{
    /**
     * 普通审批动作：发起、同意、拒绝、撤销、抄送
     */
    public const NORMAL = 'normal';

    /**
     * 预留（当前不产生）：
     * RETURN（退回）、ADD_SIGN（加签）、TRANSFER（转办）、DELEGATE（代理）、
     * TIMEOUT（超时自动处理）、SYSTEM（引擎自己的补偿动作）。
     * 加类型不需要迁移，`log_type` 是 VARCHAR 而不是枚举。
     */
    public const FUTURE_RETURN = 'return';
    public const FUTURE_ADD_SIGN = 'addSign';
    public const FUTURE_TRANSFER = 'transfer';
    public const FUTURE_DELEGATE = 'delegate';
    public const FUTURE_TIMEOUT = 'timeout';
    public const FUTURE_SYSTEM = 'system';
}
