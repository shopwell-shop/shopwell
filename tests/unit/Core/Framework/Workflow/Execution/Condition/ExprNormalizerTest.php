<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Workflow\Execution\Condition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\Condition\ExprNormalizer;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;

/**
 * 表达式归一化与求值。
 *
 * 重点不是"能算对 1 > 0"，而是三件**会静默出错**的事：
 * 1. 变量引用写错形式时是否报错（而不是当成字符串常量比较）；
 * 2. 变量不在表单里是否报错（否则运行期取到 null，条件恒假）；
 * 3. 不同类型比较的语义（`"5000" == 5000` 必须为真，表单可能把数字传成字符串）。
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
        // `amount > 5000` 能跑，但设计器渲染不出条件标签，用户看不到自己配了什么
        $this->expectException(WorkflowExecutionException::class);

        $this->normalizer->assertValid('amount > 5000', ['amount']);
    }

    public function testFunctionCallIsRejected(): void
    {
        $this->expectException(WorkflowExecutionException::class);

        $this->normalizer->assertValid('${form.amount} > 1; DROP TABLE user', ['amount']);
    }
}
