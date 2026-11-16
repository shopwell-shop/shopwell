<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Workflow\Execution\Condition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\Condition\ConditionExpressionParser;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ConditionExpressionParser::class)]
class ConditionExpressionParserTest extends TestCase
{
    public function testInstructionsPreserveComparisonAndLogicalPrecedence(): void
    {
        $instructions = (new ConditionExpressionParser())->parse('${form.a} == 1 || ${form.b} > 2 && ${form.c} != null');

        static::assertSame(['variable', 'literal', '==', 'variable', 'literal', '>', 'variable', 'literal', '!=', '&&', '||'], array_column($instructions, 'type'));
        static::assertSame(['a', 1, '==', 'b', 2, '>', 'c', null, '!=', '&&', '||'], array_column($instructions, 'value'));
    }

    public function testLibraryParsingDepthAlsoBoundsLongFlatExpressions(): void
    {
        $expression = implode(' || ', array_fill(0, 260, '${form.amount} > 0'));
        try {
            (new ConditionExpressionParser())->parse($expression);
            static::fail('The library parsing depth must bound long flat expressions.');
        } catch (WorkflowExecutionException $exception) {
            static::assertStringContainsString('maximum nesting level is 256', $exception->getMessage());
        }
    }

    public function testIntegerLiteralsPreserveTheirOriginalConversion(): void
    {
        $instructions = (new ConditionExpressionParser())->parse('${form.amount} == 999999999999999999999999 || ${form.amount} == -999999999999999999999999');

        static::assertSame(\PHP_INT_MAX, $instructions[1]['value']);
        static::assertSame(\PHP_INT_MIN, $instructions[4]['value']);
    }
}
