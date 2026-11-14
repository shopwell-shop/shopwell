<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Workflow\Execution\Condition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\Condition\ComparisonConditionEvaluator;
use Shopwell\Core\Framework\Workflow\Execution\Condition\ExprNormalizer;
use Shopwell\Core\Framework\Workflow\Execution\Condition\WorkflowConditionEvaluator;
use Shopwell\Core\Framework\Workflow\Publication\Approval\ApprovalCondition;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(WorkflowConditionEvaluator::class)]
class WorkflowConditionEvaluatorTest extends TestCase
{
    public function testSupportsTypedWorkflowConditionsAndComparisonExpressions(): void
    {
        $conditions = new ApprovalCondition();
        $evaluator = new WorkflowConditionEvaluator(new ComparisonConditionEvaluator(new ExprNormalizer()), $conditions);
        static::assertTrue($evaluator->evaluate('${form.amount} > 5000', ['amount' => 5001]));
        $expr = $conditions->encode(['field' => 'request.amount', 'fieldType' => 'amount', 'operator' => 'greaterThan', 'value' => '5000'], [['key' => 'amount', 'label' => 'Amount', 'type' => 'amount']]);
        static::assertTrue($evaluator->evaluate($expr, ['amount' => '5000.01']));
        static::assertFalse($evaluator->evaluate($expr, ['amount' => '5000.00']));
    }
}
