<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Validator\Rule;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\Graph\GraphData;
use Shopwell\Core\Framework\Workflow\Execution\Validator\ValidationContext;
use Shopwell\Core\Framework\Workflow\Execution\Validator\Violation;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowNodeTypes;

/**
 * Require exactly one start node before saving.
 *
 * @internal
 */
#[Package('framework')]
final class SingleStartRule implements GraphRuleInterface
{
    public function validate(GraphData $graph, ValidationContext $context): array
    {
        $starts = $graph->nodesOfType(WorkflowNodeTypes::START);

        return match (\count($starts)) {
            1 => [],
            0 => [Violation::error('WORKFLOW_EXECUTION_002', 'A start node is required.')],
            default => [Violation::error(
                'WORKFLOW_EXECUTION_002',
                \sprintf('Exactly one start node is required; found %d.', \count($starts)),
                $starts[1]->id
            )],
        };
    }
}
