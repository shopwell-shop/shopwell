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
 * 可达性，两个方向都要查：
 *
 * 1. **从开始节点出发能不能到这个节点** —— 到不了就是孤立节点（画在画布上但没连进来）；
 * 2. **这个节点能不能走到结束节点** —— 走不到就是死路（典型：加了审批节点忘了连到结束）。
 *
 * 只查一个方向是不够的：只查正向时，"审批节点 → 悬空"能通过（节点可达），
 * 但发起到那一步会抛 deadEnd；只查反向时，孤立节点能通过。
 *
 * 有向图的"能否到达"用 BFS，别用 DFS 递归——图深了会爆栈。
 *
 * 错误码 `WORKFLOW_EXECUTION_005`。
 */
#[Package('framework')]
final class ReachabilityRule implements GraphRuleInterface
{
    public function validate(GraphData $graph, ValidationContext $context): array
    {
        $starts = $graph->nodesOfType(WorkflowNodeTypes::START);
        $ends = $graph->nodesOfType(WorkflowNodeTypes::END);

        // 数量不对由 002/003 报，缺了也没法算可达性
        if (\count($starts) !== 1 || \count($ends) !== 1) {
            return [];
        }

        $startId = $starts[0]->id;
        $endId = $ends[0]->id;

        $forward = $this->reachableFrom($graph, [$startId]);
        $backward = $this->reachableFrom($graph, [$endId], true);

        $violations = [];

        foreach ($graph->nodeList() as $node) {
            if (!isset($forward[$node->id])) {
                $violations[] = Violation::error(
                    'WORKFLOW_EXECUTION_005',
                    '这个节点从开始节点走不到（孤立节点），请连上或用删除',
                    $node->id
                );

                continue;
            }

            if (!isset($backward[$node->id])) {
                $violations[] = Violation::error(
                    'WORKFLOW_EXECUTION_005',
                    '这个节点走不到结束节点（死路），流程会在这一步断掉',
                    $node->id
                );
            }
        }

        return $violations;
    }

    /**
     * @param list<string> $seeds
     *
     * @return array<string, true>
     */
    private function reachableFrom(GraphData $graph, array $seeds, bool $reverse = false): array
    {
        $incoming = [];

        if ($reverse) {
            // 反向图：把每条边倒过来，才能"从结束节点往回走"
            foreach ($graph->edges() as $edge) {
                $incoming[$edge->targetNodeId][] = $edge->sourceNodeId;
            }
        }

        $visited = [];
        $queue = $seeds;

        while ($queue !== []) {
            $current = array_shift($queue);

            if (isset($visited[$current]) || !$graph->hasNode($current)) {
                continue;
            }

            $visited[$current] = true;

            $next = $reverse
                ? ($incoming[$current] ?? [])
                : array_map(static fn ($edge): string => $edge->targetNodeId, $graph->outgoing($current));

            foreach ($next as $nodeId) {
                if (!isset($visited[$nodeId])) {
                    $queue[] = $nodeId;
                }
            }
        }

        return $visited;
    }
}
