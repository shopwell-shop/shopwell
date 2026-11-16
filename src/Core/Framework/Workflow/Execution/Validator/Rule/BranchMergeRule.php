<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Validator\Rule;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\Graph\GraphData;
use Shopwell\Core\Framework\Workflow\Execution\Validator\ValidationContext;
use Shopwell\Core\Framework\Workflow\Execution\Validator\Violation;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowNodeTypes;

/**
 * Warn when branches have no common successor other than the end node.
 *
 * @internal
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
                    \sprintf('After "%s", %d branches have no common node before the end. Check whether they should merge.', $node->name(), \count($outgoing)),
                    $node->id
                );
            }
        }

        return $violations;
    }

    /**
     * Return reachable nodes excluding end nodes; use a set to avoid repeated array merging.
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

            // End nodes do not count as merge points.
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
