<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Condition;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Publication\Approval\ApprovalCondition;

/**
 * @internal
 */
#[Package('framework')]
final readonly class WorkflowConditionEvaluator implements ConditionEvaluatorInterface
{
    public function __construct(private ComparisonConditionEvaluator $expressionEvaluator, private ApprovalCondition $conditions)
    {
    }

    public function evaluate(string $expr, array $formData): bool
    {
        return str_starts_with($expr, ApprovalCondition::PREFIX)
            ? $this->conditions->evaluate($expr, $formData)
            : $this->expressionEvaluator->evaluate($expr, $formData);
    }
}
