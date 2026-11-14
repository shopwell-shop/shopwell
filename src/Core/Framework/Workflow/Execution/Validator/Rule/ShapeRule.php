<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Validator\Rule;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\Executor\NodeExecutorRegistry;
use Shopwell\Core\Framework\Workflow\Execution\Graph\GraphData;
use Shopwell\Core\Framework\Workflow\Execution\Validator\ValidationContext;
use Shopwell\Core\Framework\Workflow\Execution\Validator\Violation;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowNodeTypes;

/**
 * @internal
 *
 * 形状：id 唯一、类型已知、连线两端存在、没有自环。
 *
 * 这条规则必须先跑：后面所有规则都假设"边指向的节点存在"，
 * 一旦悬空边先被别的规则碰上，报出来的错误会是一句看不懂的"节点不存在"。
 *
 * 错误码 `WORKFLOW_EXECUTION_001`。
 */
#[Package('framework')]
final class ShapeRule implements GraphRuleInterface
{
    public function __construct(private readonly ?NodeExecutorRegistry $executors = null)
    {
    }

    public function validate(GraphData $graph, ValidationContext $context): array
    {
        $violations = [];

        foreach ($graph->duplicateNodeIds() as $nodeId) {
            $violations[] = Violation::error(
                'WORKFLOW_EXECUTION_001',
                \sprintf('节点 id "%s" 重复', $nodeId),
                $nodeId
            );
        }

        $edgeIds = [];

        foreach ($graph->edges() as $edge) {
            if (isset($edgeIds[$edge->id])) {
                $violations[] = Violation::error('WORKFLOW_EXECUTION_001', \sprintf('连线 id "%s" 重复', $edge->id), null, $edge->id);
            }

            $edgeIds[$edge->id] = true;

            if ($edge->sourceNodeId === $edge->targetNodeId) {
                $violations[] = Violation::error(
                    'WORKFLOW_EXECUTION_001',
                    '连线不能指向自己',
                    $edge->sourceNodeId,
                    $edge->id
                );
            }

            if (!$graph->hasNode($edge->sourceNodeId)) {
                $violations[] = Violation::error(
                    'WORKFLOW_EXECUTION_001',
                    \sprintf('连线的起点 "%s" 不存在（悬空连线）', $edge->sourceNodeId),
                    null,
                    $edge->id
                );
            }

            if (!$graph->hasNode($edge->targetNodeId)) {
                $violations[] = Violation::error(
                    'WORKFLOW_EXECUTION_001',
                    \sprintf('连线的终点 "%s" 不存在（悬空连线）', $edge->targetNodeId),
                    null,
                    $edge->id
                );
            }
        }

        foreach ($graph->nodeList() as $node) {
            if (!($this->executors?->has($node->type) ?? \in_array($node->type, WorkflowNodeTypes::ALL, true))) {
                $violations[] = Violation::error(
                    'WORKFLOW_EXECUTION_001',
                    \sprintf('不支持的节点类型 "%s"', $node->type),
                    $node->id
                );
            }
        }

        return $violations;
    }
}
