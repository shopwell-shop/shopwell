<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution;

use Shopwell\Core\Framework\Log\Package;

/**
 * Node types in the compiled execution graph consumed by the executor registry.
 * This internal representation differs from the designer workflow document.
 * Publishers compile designer nodes into these execution types and properties.
 *
 * @internal
 */
#[Package('framework')]
final class WorkflowNodeTypes
{
    /**
     * 开始节点（发起人节点），一张图有且仅有一个
     */
    public const START = 'snaker:start';

    /**
     * 审批节点，属性决定审批人与多人审批方式
     */
    public const TASK = 'snaker:task';

    /**
     * 抄送节点：只写抄送记录，不生成待办、不阻塞流转
     */
    public const COPY = 'snaker:copy';

    /**
     * 结束节点，一张图有且仅有一个
     */
    public const END = 'snaker:end';

    /**
     * 契约允许的全部节点类型；校验器用它判断"类型未知"
     */
    public const CONDITION = 'snaker:condition';

    /**
     * 契约允许的全部节点类型；校验器用它判断"类型未知"
     */
    public const ALL = [self::START, self::TASK, self::COPY, self::END, self::CONDITION];

    /**
     * 预留（当前**不实现**，只是把词表位置留好）：
     * SUBPROCESS（子流程）。
     * 加它们时同时要加执行器，否则 `NodeExecutorRegistry` 会在运行期抛 unsupportedNodeType。
     */
    public const FUTURE_SUBPROCESS = 'snaker:subprocess';
}
