<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Graph;

use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 *
 * 契约里的一条连线。
 *
 * 条件**只存在于连线上**（`properties.expr`），不在节点上——这是与后端引擎
 * 约定的硬规则：一个审批节点后面有几条出边，就有几种走法，条件属于"走法"
 * 而不是"节点"。
 *
 * 表达式原样保留（`${form.amount} > 5000`），归一化交给
 * {@see \Shopwell\Core\Framework\Workflow\Execution\Condition\ComparisonConditionEvaluator} 做。
 * 理由：DTO 是纯数据，不注入任何服务；否则图对象会拖着一个求值器到处跑。
 */
#[Package('framework')]
final class GraphEdge
{
    /**
     * @param array<string, mixed> $properties
     */
    public function __construct(
        public readonly string $id,
        public readonly string $sourceNodeId,
        public readonly string $targetNodeId,
        public readonly array $properties,
    ) {
    }

    /**
     * 条件表达式；无条件连线返回 null（代表默认分支）。
     *
     * 只 trim 成空串的也算默认分支：设计器里删掉条件后可能留下 `"expr": "  "`，
     * 若当成"有条件但恒假"处理，这条边永远走不到，流程会莫名断在这里。
     */
    public function expr(): ?string
    {
        $expr = $this->properties['expr'] ?? null;

        if (!\is_string($expr)) {
            return null;
        }

        $expr = trim($expr);

        return $expr === '' ? null : $expr;
    }

    /**
     * @return array{id: string, sourceNodeId: string, targetNodeId: string, properties: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'sourceNodeId' => $this->sourceNodeId,
            'targetNodeId' => $this->targetNodeId,
            'properties' => $this->properties,
        ];
    }
}
