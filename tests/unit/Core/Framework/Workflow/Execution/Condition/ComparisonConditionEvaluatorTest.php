<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Workflow\Execution\Condition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\Condition\ComparisonConditionEvaluator;
use Shopwell\Core\Framework\Workflow\Execution\Condition\ExprNormalizer;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ComparisonConditionEvaluator::class)]
class ComparisonConditionEvaluatorTest extends TestCase
{
    private ComparisonConditionEvaluator $evaluator;

    protected function setUp(): void
    {
        $this->evaluator = new ComparisonConditionEvaluator(new ExprNormalizer());
    }

    public function testEvaluatesNumericComparison(): void
    {
        static::assertTrue($this->evaluator->evaluate('${form.amount} > 5000', ['amount' => 10000]));
        static::assertFalse($this->evaluator->evaluate('${form.amount} > 5000', ['amount' => 100]));
    }

    public function testComparesNumericStringAsNumber(): void
    {
        // 表单可能把 number 字段以字符串提交，按字符串比会让 == 5000 意外为假
        static::assertTrue($this->evaluator->evaluate('${form.amount} == 5000', ['amount' => '5000']));
    }

    public function testEvaluatesStringEquality(): void
    {
        static::assertTrue($this->evaluator->evaluate('${form.type} == \'采购\'', ['type' => '采购']));
        static::assertFalse($this->evaluator->evaluate('${form.type} == \'采购\'', ['type' => '报销']));
    }

    public function testMissingFieldIsNotGreaterThanAnything(): void
    {
        static::assertFalse($this->evaluator->evaluate('${form.amount} > 0', []));
    }

    public function testMissingFieldIsNotEqualToAnything(): void
    {
        static::assertFalse($this->evaluator->evaluate('${form.amount} == 0', []));
    }

    public function testCombinesConditions(): void
    {
        static::assertTrue($this->evaluator->evaluate(
            '${form.amount} > 1000 && ${form.type} == \'采购\'',
            ['amount' => 2000, 'type' => '采购']
        ));

        static::assertFalse($this->evaluator->evaluate(
            '${form.amount} > 1000 && ${form.type} == \'采购\'',
            ['amount' => 2000, 'type' => '报销']
        ));
    }

    public function testUnparsableExpressionFailsLoudly(): void
    {
        // 解析失败**不能**当成 false：否则分支会静默走默认路，
        // 症状是"条件配了但不生效"，比直接报错难查得多
        $this->expectException(WorkflowExecutionException::class);

        $this->evaluator->evaluate('${form.amount} >< 1', ['amount' => 5]);
    }
}
