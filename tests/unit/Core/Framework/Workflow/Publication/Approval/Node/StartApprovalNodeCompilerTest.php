<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Workflow\Publication\Approval\Node;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Definition\Input\WorkflowInputSchema;
use Shopwell\Core\Framework\Workflow\Publication\Approval\Node\ApprovalNodeCompilationContext;
use Shopwell\Core\Framework\Workflow\Publication\Approval\Node\ApprovalNodeRole;
use Shopwell\Core\Framework\Workflow\Publication\Approval\Node\StartApprovalNodeCompiler;
use Shopwell\Core\Framework\Workflow\WorkflowException;
use Shopwell\Tests\Unit\Core\Framework\Workflow\_fixtures\ApprovalRegistryFixture;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(StartApprovalNodeCompiler::class)]
class StartApprovalNodeCompilerTest extends TestCase
{
    public function testNormalizesRequestFieldsForDownstreamCompilers(): void
    {
        $compiler = new StartApprovalNodeCompiler(new WorkflowInputSchema(), ApprovalRegistryFixture::translator());
        $compiled = $compiler->compile(new ApprovalNodeCompilationContext('start', [
            'formFields' => [['key' => 'amount', 'label' => 'Amount', 'type' => 'amount']],
        ], '/nodes/0/config'));

        static::assertSame('core:start', $compiler->getNodeType());
        static::assertSame(1, $compiler->getNodeVersion());
        static::assertSame(ApprovalNodeRole::START, $compiler->getRole());
        static::assertSame([], $compiled->inputPorts);
        static::assertSame('amount', $compiled->properties['inputSchema'][0]['key']);
        static::assertSame(['out' => []], $compiled->outputPorts);
    }

    public function testDoesNotDiscardAnUnsupportedInputType(): void
    {
        $compiler = new StartApprovalNodeCompiler(new WorkflowInputSchema(), ApprovalRegistryFixture::translator());
        $this->expectException(WorkflowException::class);

        $compiler->compile(new ApprovalNodeCompilationContext('start', ['inputType' => 'example:input'], '/nodes/0/config'));
    }
}
