<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Workflow\Publication\Approval;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;
use Shopwell\Core\Framework\Workflow\Publication\Approval\ApprovalCondition;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ApprovalCondition::class)]
class ApprovalConditionTest extends TestCase
{
    #[DataProvider('comparisons')]
    public function testTypedComparisons(string $type, string $operator, string $expected, mixed $actual, bool $matches): void
    {
        $conditions = new ApprovalCondition();
        $expression = $conditions->encode(['field' => 'request.value', 'fieldType' => $type, 'operator' => $operator, 'value' => $expected], [['key' => 'value', 'label' => 'Value', 'type' => $type]]);
        static::assertSame($matches, $conditions->evaluate($expression, ['value' => $actual]));
    }

    /**
     * @return iterable<string, array{string, string, string, mixed, bool}>
     */
    public static function comparisons(): iterable
    {
        yield 'below boundary uses default' => ['amount', 'greaterThan', '5000', '4999.99', false];
        yield 'exact boundary uses default' => ['amount', 'greaterThan', '5000', '5000.00', false];
        yield 'one cent above requires finance' => ['amount', 'greaterThan', '5000', '5000.01', true];
        yield 'large amounts retain cents' => ['amount', 'greaterThan', '999999999999.98', '999999999999.99', true];
        yield 'numeric equality ignores decimal scale' => ['amount', 'equals', '5000', '5000.00', true];
        yield 'negative numeric comparison' => ['number', 'lessThan', '-0.000001', '-0.000002', true];
        yield 'inclusive greater bound' => ['number', 'greaterThanOrEqual', '2', '2.000000', true];
        yield 'inclusive lesser date bound' => ['date', 'lessThanOrEqual', '2026-12-31', '2026-12-31', true];
        yield 'dates compare chronologically' => ['date', 'greaterThan', '2026-01-31', '2026-02-01', true];
        yield 'text equality is strict' => ['text', 'equals', '01', '1', false];
        yield 'quotes and operators remain literal text' => ['text', 'equals', 'a\' || true || \'', 'a\' || true || \'', true];
        yield 'literal inequality' => ['text', 'notEquals', 'a', 'b', true];
        yield 'missing optional does not match inequality' => ['text', 'notEquals', 'a', null, false];
        yield 'missing optional does not match equality' => ['text', 'equals', '', null, false];
        yield 'missing optional is empty' => ['amount', 'isEmpty', '', null, true];
        yield 'missing optional is not nonempty' => ['date', 'isNotEmpty', '', null, false];
        yield 'zero is nonempty' => ['amount', 'isNotEmpty', '', '0.00', true];
    }

    public function testRejectsReferenceRemovedFromSchema(): void
    {
        $this->expectException(WorkflowExecutionException::class);
        (new ApprovalCondition())->encode(['field' => 'request.removed', 'fieldType' => 'text', 'operator' => 'equals', 'value' => 'x'], []);
    }

    public function testRejectsRelationalOperatorAfterFieldChangesToText(): void
    {
        $this->expectException(WorkflowExecutionException::class);
        (new ApprovalCondition())->encode(['field' => 'request.amount', 'fieldType' => 'amount', 'operator' => 'greaterThan', 'value' => '5000'], [['key' => 'amount', 'label' => 'Amount', 'type' => 'text']]);
    }

    public function testRejectsMalformedEncodedCondition(): void
    {
        $this->expectException(WorkflowExecutionException::class);
        (new ApprovalCondition())->evaluate(ApprovalCondition::PREFIX . '{', []);
    }
}
