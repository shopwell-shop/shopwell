<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Validator\Rule;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\Graph\GraphData;
use Shopwell\Core\Framework\Workflow\Execution\Validator\ValidationContext;
use Shopwell\Core\Framework\Workflow\Execution\Validator\Violation;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowNodeTypes;

/**
 * Check reachability from the start and to the end using breadth-first traversal.
 *
 * @internal
 */
#[Package('framework')]
final class ReachabilityRule implements GraphRuleInterface
{
    public function validate(GraphData $graph, ValidationContext $context): array
    {
        $starts = $graph->nodesOfType(WorkflowNodeTypes::START);
        $ends = $graph->nodesOfType(WorkflowNodeTypes::END);

        // Start and end counts are reported by their dedicated rules.
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
                    'This node is unreachable from the start. Connect or delete it.',
                    $node->id
                );

                continue;
            }

            if (!isset($backward[$node->id])) {
                $violations[] = Violation::error(
                    'WORKFLOW_EXECUTION_005',
                    'This node cannot reach the end; execution would stop here.',
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
            // Reverse each edge to traverse back from the end.
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
