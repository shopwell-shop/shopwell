<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Workflow\Execution\Condition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\Condition\ExprNormalizer;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;

/**
 * Cover invalid references, unknown form fields and numeric string comparison semantics.
 *
 * @internal
 */
#[Package('framework')]
#[CoversClass(ExprNormalizer::class)]
class ExprNormalizerTest extends TestCase
{
    private ExprNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new ExprNormalizer();
    }

    public function testNormalizeReplacesContractReference(): void
    {
        static::assertSame(
            'amount > 5000',
            $this->normalizer->normalize('${form.amount} > 5000')
        );
    }

    public function testNormalizeToleratesSpacesInsideBraces(): void
    {
        static::assertSame(
            'type == \'采购\'',
            $this->normalizer->normalize('${ form.type } == \'采购\'')
        );
    }

    public function testVariablesAreListedInOrder(): void
    {
        static::assertSame(
            ['amount', 'type'],
            $this->normalizer->variables('${form.amount} > 1 && ${form.type} == \'A\'')
        );
    }

    public function testUnknownVariableIsRejectedOnSave(): void
    {
        $this->expectException(WorkflowExecutionException::class);

        $this->normalizer->assertValid('${form.amount} > 5000', ['reason']);
    }

    public function testBareVariableNameIsRejected(): void
    {
        // Bare field references cannot be rendered by the designer and must be rejected.
        $this->expectException(WorkflowExecutionException::class);

        $this->normalizer->assertValid('amount > 5000', ['amount']);
    }

    public function testFunctionCallIsRejected(): void
    {
        $this->expectException(WorkflowExecutionException::class);

        $this->normalizer->assertValid('${form.amount} > 1; DROP TABLE user', ['amount']);
    }
}
