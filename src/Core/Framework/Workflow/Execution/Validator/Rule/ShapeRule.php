<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Validator\Rule;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\Executor\NodeExecutorRegistry;
use Shopwell\Core\Framework\Workflow\Execution\Graph\GraphData;
use Shopwell\Core\Framework\Workflow\Execution\Validator\ValidationContext;
use Shopwell\Core\Framework\Workflow\Execution\Validator\Violation;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowNodeTypes;

/**
 * Validate IDs, node types, edge endpoints and self-loops before running dependent rules.
 *
 * @internal
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
                \sprintf('Duplicate node ID "%s".', $nodeId),
                $nodeId
            );
        }

        $edgeIds = [];

        foreach ($graph->edges() as $edge) {
            if (mb_strlen($edge->id) > 64) {
                $violations[] = Violation::error('WORKFLOW_EXECUTION_001', 'Runtime edge IDs must not exceed 64 characters.', $edge->sourceNodeId, $edge->id);
            }

            if (isset($edgeIds[$edge->id])) {
                $violations[] = Violation::error('WORKFLOW_EXECUTION_001', \sprintf('Duplicate edge ID "%s".', $edge->id), null, $edge->id);
            }

            $edgeIds[$edge->id] = true;

            if ($edge->sourceNodeId === $edge->targetNodeId) {
                $violations[] = Violation::error(
                    'WORKFLOW_EXECUTION_001',
                    'An edge cannot connect a node to itself.',
                    $edge->sourceNodeId,
                    $edge->id
                );
            }

            if (!$graph->hasNode($edge->sourceNodeId)) {
                $violations[] = Violation::error(
                    'WORKFLOW_EXECUTION_001',
                    \sprintf('Edge source node "%s" does not exist.', $edge->sourceNodeId),
                    null,
                    $edge->id
                );
            }

            if (!$graph->hasNode($edge->targetNodeId)) {
                $violations[] = Violation::error(
                    'WORKFLOW_EXECUTION_001',
                    \sprintf('Edge target node "%s" does not exist.', $edge->targetNodeId),
                    null,
                    $edge->id
                );
            }
        }

        foreach ($graph->nodeList() as $node) {
            if (mb_strlen($node->id) > 64) {
                $violations[] = Violation::error('WORKFLOW_EXECUTION_001', 'Runtime node IDs must not exceed 64 characters.', $node->id);
            }

            if (!($this->executors?->has($node->type) ?? \in_array($node->type, WorkflowNodeTypes::ALL, true))) {
                $violations[] = Violation::error(
                    'WORKFLOW_EXECUTION_001',
                    \sprintf('Unsupported node type "%s".', $node->type),
                    $node->id
                );
            }
        }

        return $violations;
    }
}
