<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Workflow\Publication\Approval\Node;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Publication\Approval\Node\ApprovalNodeCompilationContext;
use Shopwell\Core\Framework\Workflow\Publication\Approval\Node\ApprovalNodeRole;
use Shopwell\Core\Framework\Workflow\Publication\Approval\Node\EndApprovalNodeCompiler;
use Shopwell\Core\Framework\Workflow\WorkflowException;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(EndApprovalNodeCompiler::class)]
class EndApprovalNodeCompilerTest extends TestCase
{
    public function testAnEmptyJsonObjectCompilesWithoutOutputPorts(): void
    {
        $compiler = new EndApprovalNodeCompiler();
        $compiled = $compiler->compile(new ApprovalNodeCompilationContext('end', new \stdClass(), '/nodes/3/config'));

        static::assertSame('core:end', $compiler->getNodeType());
        static::assertSame(1, $compiler->getNodeVersion());
        static::assertSame(ApprovalNodeRole::END, $compiler->getRole());
        static::assertSame([], $compiled->outputPorts);
        static::assertSame([], $compiled->terminalPorts);
    }

    public function testDoesNotSilentlyDiscardEndConfiguration(): void
    {
        $compiler = new EndApprovalNodeCompiler();
        $this->expectException(WorkflowException::class);

        $compiler->compile(new ApprovalNodeCompilationContext('end', ['result' => 'ignored'], '/nodes/3/config'));
    }
}
