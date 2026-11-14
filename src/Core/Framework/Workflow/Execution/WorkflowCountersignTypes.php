<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\Graph\GraphNode;

/**
 * @internal
 *
 * 多人审批方式（`node.properties.countersignType`）。
 *
 * 审批语义完全由 {@see CounterSignCalculator} 实现，引擎里不允许出现第二处计数。
 * 这里的常量只负责"叫什么"和"缺省是什么"。
 */
#[Package('framework')]
final class WorkflowCountersignTypes
{
    /**
     * 单人：节点只产生一条任务，那个人说了算
     */
    public const SINGLE = 'single';

    /**
     * 或签：任一同意即通过，**全部**拒绝才拒绝
     */
    public const OR_SIGN = 'orSign';

    /**
     * 首个决定：第一位提交同意或拒绝后立即结束当前节点
     */
    public const FIRST_DECISION = 'firstDecision';

    /**
     * 会签：全部同意才通过，任一拒绝即拒绝
     */
    public const COUNTERSIGN = 'countersign';

    /**
     * 顺序会签：按顺序逐个审批，最后一位同意才算通过
     */
    public const ORDER_SIGN = 'orderSign';

    public const ALL = [self::SINGLE, self::OR_SIGN, self::FIRST_DECISION, self::COUNTERSIGN, self::ORDER_SIGN];

    /**
     * 旧模板里没写这个属性时的缺省值：保守地当成单人，而不是悄悄变成会签
     */
    public const DEFAULT = self::SINGLE;

    /**
     * 从节点属性读出多人审批方式。
     *
     * 未知值**不静默降级**：写成 `countersign ` (尾空格) 或 `or_sign` 时，
     * 如果降级成单人，流程会"跑得通但结果是错的"，比直接报错难查一百倍。
     */
    public static function fromNode(GraphNode $node): string
    {
        $type = $node->property('countersignType');

        if ($type === null || $type === '') {
            return self::DEFAULT;
        }

        if (!\is_string($type) || !\in_array($type, self::ALL, true)) {
            throw WorkflowExecutionException::unsupportedCountersignType(\is_string($type) ? $type : get_debug_type($type));
        }

        return $type;
    }

    /**
     * 该类型是否可能一次产生多条任务。
     * 只用于"要不要按多人规则去解析审批人"这类分支，不参与判定。
     */
    public static function isMultiPerson(string $type): bool
    {
        return $type !== self::SINGLE;
    }
}
