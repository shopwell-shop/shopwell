<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Workflow\Publication\Approval\Node;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Publication\Approval\ApprovalCondition;
use Shopwell\Core\Framework\Workflow\Publication\Approval\Node\ApprovalNodeCompilationContext;
use Shopwell\Core\Framework\Workflow\Publication\Approval\Node\ApprovalNodeRole;
use Shopwell\Core\Framework\Workflow\Publication\Approval\Node\ConditionApprovalNodeCompiler;
use Shopwell\Core\Framework\Workflow\WorkflowException;
use Shopwell\Tests\Unit\Core\Framework\Workflow\_fixtures\ApprovalRegistryFixture;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ConditionApprovalNodeCompiler::class)]
class ConditionApprovalNodeCompilerTest extends TestCase
{
    public function testCompilesOrderedExpressionsAgainstTheStartSchema(): void
    {
        $conditions = new ApprovalCondition();
        $compiler = new ConditionApprovalNodeCompiler($conditions, ApprovalRegistryFixture::translator());
        $compiled = $compiler->compile(new ApprovalNodeCompilationContext('condition', [
            'branches' => [[
                'id' => 'branch-finance', 'label' => 'Finance', 'field' => 'request.amount',
                'fieldType' => 'amount', 'operator' => 'greaterThan', 'value' => '5000',
            ]],
        ], '/nodes/2/config', inputSchema: [['key' => 'amount', 'label' => 'Amount', 'type' => 'amount']]));

        static::assertSame('core:condition', $compiler->getNodeType());
        static::assertSame(1, $compiler->getNodeVersion());
        static::assertSame(ApprovalNodeRole::ACTIVITY, $compiler->getRole());
        static::assertSame(['branch-finance', 'fallback'], array_keys($compiled->outputPorts));
        $expression = $compiled->outputPorts['branch-finance']['expr'];
        static::assertIsString($expression);
        static::assertTrue($conditions->evaluate($expression, ['amount' => '5000.01']));
        static::assertFalse($conditions->evaluate($expression, ['amount' => '5000.00']));
    }

    public function testRejectsAFieldMissingFromTheStartSchema(): void
    {
        $compiler = new ConditionApprovalNodeCompiler(new ApprovalCondition(), ApprovalRegistryFixture::translator());
        $this->expectException(WorkflowException::class);

        $compiler->compile(new ApprovalNodeCompilationContext('condition', [
            'branches' => [[
                'id' => 'branch-finance', 'label' => 'Finance', 'field' => 'request.amount',
                'fieldType' => 'amount', 'operator' => 'greaterThan', 'value' => '5000',
            ]],
        ], '/nodes/2/config'));
    }
}
