<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Workflow\Execution\Condition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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
        // Form number fields may arrive as numeric strings.
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
        // Parsing failures must throw instead of silently selecting a default branch.
        $this->expectException(WorkflowExecutionException::class);

        $this->evaluator->evaluate('${form.amount} >< 1', ['amount' => 5]);
    }

    /**
     * @param array<string, mixed> $values
     */
    #[DataProvider('supportedExpressions')]
    public function testValidationAndExecutionUseTheSameGrammar(string $expression, array $values, bool $expected): void
    {
        (new ExprNormalizer())->assertValid($expression, array_keys($values));

        static::assertSame($expected, $this->evaluator->evaluate($expression, $values));
    }

    /**
     * @return iterable<string, array{string, array<string, mixed>, bool}>
     */
    public static function supportedExpressions(): iterable
    {
        yield 'logical range' => ['${form.amount} > 1 && ${form.amount} < 10', ['amount' => 5], true];
        yield 'Chinese literal' => ['${form.note} == "采购"', ['note' => '采购'], true];
        yield 'comparison inside literal' => ['${form.note} == "a>=b"', ['note' => 'a>=b'], true];
        yield 'nested parentheses and precedence' => ['(${form.amount} < 0 || (${form.amount} > 1 && ${form.amount} < 10))', ['amount' => 5], true];
        yield 'parentheses and logical operators inside literal' => ['${form.note} == "(a && b) || c"', ['note' => '(a && b) || c'], true];
        yield 'escaped quote' => ['${form.note} == "a\"b"', ['note' => 'a"b'], true];
        yield 'escaped backslash before closing quote' => ['${form.note} == "a\\\\"', ['note' => 'a\\'], true];
        yield 'reference syntax inside literal is text' => ['${form.note} == "${form.other}"', ['note' => '${form.other}'], true];
        yield 'false disjunction' => ['${form.amount} < 0 || ${form.amount} > 10', ['amount' => 5], false];
        yield 'nesting limit remains executable' => [str_repeat('(', 64) . '${form.amount} > 0' . str_repeat(')', 64), ['amount' => 5], true];
        yield 'literal condition needs no form fields' => ['true == true && null != false', [], true];
        yield 'mixed case literals' => ['TrUe == true && NuLl != false', [], true];
        yield 'keyword field remains a reference' => ['${form.true} == 1', ['true' => '1'], true];
        yield 'negative leading decimal' => ['${form.amount} == -.5', ['amount' => '-0.5'], true];
        yield 'raw newline remains string content' => ["\${form.note} == \"a\nb\"", ['note' => "a\nb"], true];
        yield 'escaped backslash before plain letter' => ['${form.note} == "a\\\\n"', ['note' => 'a\\n'], true];
        yield 'false string preserves boolean comparison' => ['${form.flag} == true', ['flag' => 'false'], false];
        yield 'booleans cannot be ordered' => ['${form.amount} > false', ['amount' => 1], false];
    }

    #[DataProvider('unsupportedExpressions')]
    public function testValidationAndExecutionRejectUnsupportedGrammar(string $expression): void
    {
        try {
            (new ExprNormalizer())->assertValid($expression, ['amount']);
            static::fail('Validation accepted an unsupported expression');
        } catch (WorkflowExecutionException) {
        }

        $this->expectException(WorkflowExecutionException::class);
        $this->evaluator->evaluate($expression, ['amount' => 5]);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unsupportedExpressions(): iterable
    {
        yield 'arithmetic' => ['${form.amount} + 1 > 2'];
        yield 'unmatched opening parenthesis' => ['(${form.amount} > 2'];
        yield 'unmatched closing parenthesis' => ['${form.amount} > 2)'];
        yield 'missing logical operand' => ['${form.amount} > 2 &&'];
        yield 'comparison chain' => ['${form.amount} > 2 > 1'];
        yield 'unclosed string' => ['${form.amount} == "oops'];
        yield 'invalid branch cannot be skipped' => ['${form.amount} > 0 || invalid()'];
        yield 'deeply nested input is bounded' => [str_repeat('(', 65) . '${form.amount} > 0' . str_repeat(')', 65)];
        yield 'empty expression' => [''];
        yield 'whitespace expression' => [" \t\n"];
        yield 'reserved field name' => ['${form._amount} > 2'];
        yield 'invalid field name' => ['${form.2amount} > 2'];
        yield 'unsupported reference scope' => ['${user.amount} > 2'];
        yield 'bare identifier' => ['amount > 2'];
        yield 'unknown string escape' => ['${form.amount} == "a\\n"'];
        yield 'exponent notation' => ['${form.amount} == 1e3'];
        yield 'numeric separators' => ['${form.amount} == 1_000'];
        yield 'trailing decimal point' => ['${form.amount} == 1.'];
        yield 'comments between tokens' => ['${form.amount} /* comment */ == 1'];
        yield 'comment before expression' => ['/* comment */ ${form.amount} == 1'];
        yield 'comment after string literal' => ['${form.amount} == "1" /* comment */'];
        yield 'parenthesized field operand' => ['(${form.amount}) == 1'];
        yield 'parenthesized literal operand' => ['${form.amount} == (1)'];
        yield 'separated negative sign' => ['${form.amount} == - 1'];
        yield 'negated grouped literal' => ['${form.amount} == -(1)'];
        yield 'bare generated alias cannot reuse a reference' => ['${form.amount} == _workflow_0'];
        yield 'method access' => ['${form.amount}.value() == 1'];
        yield 'array access' => ['${form.amount}[0] == 1'];
    }
}
