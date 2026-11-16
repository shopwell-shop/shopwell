<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Validator\Rule;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\Graph\GraphData;
use Shopwell\Core\Framework\Workflow\Execution\Validator\ValidationContext;
use Shopwell\Core\Framework\Workflow\Execution\Validator\Violation;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowNodeTypes;

/**
 * Require one end node with no outgoing edges.
 *
 * @internal
 */
#[Package('framework')]
final class SingleEndRule implements GraphRuleInterface
{
    public function validate(GraphData $graph, ValidationContext $context): array
    {
        $violations = [];
        $ends = $graph->nodesOfType(WorkflowNodeTypes::END);

        if (\count($ends) === 0) {
            $violations[] = Violation::error('WORKFLOW_EXECUTION_003', 'An end node is required.');
        }

        if (\count($ends) > 1) {
            $violations[] = Violation::error(
                'WORKFLOW_EXECUTION_003',
                \sprintf('Exactly one end node is required; found %d. Merge branches into the same end node.', \count($ends)),
                $ends[1]->id
            );
        }

        foreach ($ends as $end) {
            if ($graph->outgoing($end->id) !== []) {
                $violations[] = Violation::error(
                    'WORKFLOW_EXECUTION_003',
                    'An end node cannot have outgoing edges; subsequent nodes would never execute.',
                    $end->id
                );
            }
        }

        return $violations;
    }
}
