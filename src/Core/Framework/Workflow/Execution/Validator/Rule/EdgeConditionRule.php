<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Validator\Rule;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\Condition\ExprNormalizer;
use Shopwell\Core\Framework\Workflow\Execution\Graph\GraphData;
use Shopwell\Core\Framework\Workflow\Execution\Validator\ValidationContext;
use Shopwell\Core\Framework\Workflow\Execution\Validator\Violation;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;
use Shopwell\Core\Framework\Workflow\Publication\Approval\ApprovalCondition;

/**
 * Validate edge expression syntax and references against the form schema.
 *
 * @internal
 */
#[Package('framework')]
final class EdgeConditionRule implements GraphRuleInterface
{
    public function __construct(private readonly ExprNormalizer $normalizer, private readonly ApprovalCondition $conditions = new ApprovalCondition())
    {
    }

    public function validate(GraphData $graph, ValidationContext $context): array
    {
        $violations = [];

        foreach ($graph->edges() as $edge) {
            $expr = $edge->expr();

            if ($expr === null) {
                continue;   // An unconditional edge is a valid default branch.
            }

            try {
                if (str_starts_with($expr, ApprovalCondition::PREFIX)) {
                    $condition = $this->conditions->decode($expr);
                    $field = $condition['field'];
                    if (!$context->hasFormKey($field['key']) || ($context->formTypes[$field['key']] ?? null) !== $field['type']) {
                        throw WorkflowExecutionException::invalidGraph('The condition field does not match the form schema.');
                    }
                } else {
                    $this->normalizer->assertValid($expr, $context->formKeys);
                }
            } catch (WorkflowExecutionException $exception) {
                $violations[] = Violation::error(
                    'WORKFLOW_EXECUTION_007',
                    \sprintf('Invalid edge condition: %s', $exception->getMessage()),
                    $edge->sourceNodeId,
                    $edge->id
                );
            }
        }

        return $violations;
    }
}
