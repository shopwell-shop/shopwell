<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Validator\Rule;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\Graph\GraphData;
use Shopwell\Core\Framework\Workflow\Execution\Validator\ValidationContext;
use Shopwell\Core\Framework\Workflow\Execution\Validator\Violation;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowNodeTypes;

/**
 * @internal
 *
 * 分支是否汇聚 —— **只警告，不拦截保存**。
 *
 * 钉钉允许"每个分支各自走到结束"，所以这不是错误。但多数情况下用户是
 * 忘了把分支合回来，导致后面的审批节点只挂在其中一条路上。
 * 提示一句"确认是否合并"，比强行拦住更有用。
 *
 * ## 判据：分支之间有没有"除结束节点之外"的共同后继
 *
 * 只汇合在结束节点上不算汇聚——那正是"各自走到结束"的形状。
 * 所以先算每个分支的后代集合，**剔除结束节点**，再求交集：
 * 交集为空就提示。
 *
 * 这条规则只对出度 > 1 的节点生效；单线流程不产生任何提示。
 *
 * 错误码 `WORKFLOW_EXECUTION_101`（1xx 段专用于 warning）。
 */
#[Package('framework')]
final class BranchMergeRule implements GraphRuleInterface
{
    public function validate(GraphData $graph, ValidationContext $context): array
    {
        $violations = [];

        foreach ($graph->nodeList() as $node) {
            $outgoing = $graph->outgoing($node->id);

            if (\count($outgoing) < 2) {
                continue;
            }

            /** @var array<string, true>|null $common */
            $common = null;

            foreach ($outgoing as $edge) {
                $descendants = $this->descendantsOf($graph, $edge->targetNodeId);

                $common = $common === null
                    ? $descendants
                    : array_intersect_key($common, $descendants);

                if ($common === []) {
                    break;
                }
            }

            if ($common === []) {
                $violations[] = Violation::warning(
                    'WORKFLOW_EXECUTION_101',
                    \sprintf('"%s" 之后有 %d 条分支，除结束节点外没有共同节点，请确认是否要合并', $node->name(), \count($outgoing)),
                    $node->id
                );
            }
        }

        return $violations;
    }

    /**
     * 从某个节点出发能走到的全部节点，**不含结束节点**（理由见类注释）。
     *
     * 用数组做集合而不是 `array_unique` 反复合并：节点多时后者是 O(n²)。
     *
     * @return array<string, true>
     */
    private function descendantsOf(GraphData $graph, string $startId): array
    {
        $visited = [];
        $queue = [$startId];

        while ($queue !== []) {
            $current = array_shift($queue);

            if (isset($visited[$current]) || !$graph->hasNode($current)) {
                continue;
            }

            // 结束节点不算"汇聚点"，但要继续遍历它的出边（正常为 0 条）
            if ($graph->node($current)->type === WorkflowNodeTypes::END) {
                continue;
            }

            $visited[$current] = true;

            foreach ($graph->outgoing($current) as $edge) {
                $queue[] = $edge->targetNodeId;
            }
        }

        return $visited;
    }
}
