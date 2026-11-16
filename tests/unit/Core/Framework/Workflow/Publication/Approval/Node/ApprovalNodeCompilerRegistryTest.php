<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Workflow\Publication\Approval\Node;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;
use Shopwell\Core\Framework\Workflow\Publication\Approval\Node\ApprovalNodeCompiler;
use Shopwell\Core\Framework\Workflow\Publication\Approval\Node\ApprovalNodeCompilerRegistry;
use Shopwell\Core\Framework\Workflow\WorkflowException;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ApprovalNodeCompilerRegistry::class)]
class ApprovalNodeCompilerRegistryTest extends TestCase
{
    public function testResolvesExactVersionsWithoutFallingBack(): void
    {
        $first = static::createStub(ApprovalNodeCompiler::class);
        $first->method('getNodeType')->willReturn('example:node');
        $first->method('getNodeVersion')->willReturn(1);
        $second = static::createStub(ApprovalNodeCompiler::class);
        $second->method('getNodeType')->willReturn('example:node');
        $second->method('getNodeVersion')->willReturn(2);
        $registry = new ApprovalNodeCompilerRegistry([$first, $second]);

        static::assertSame($first, $registry->find('example:node', 1));
        static::assertSame($second, $registry->find('example:node', 2));
        static::assertNull($registry->find('example:node', 3));
        static::assertTrue($registry->hasType('example:node'));
        static::assertFalse($registry->hasType('example:missing'));
    }

    public function testDuplicateRegistrationIsRejected(): void
    {
        $compiler = static::createStub(ApprovalNodeCompiler::class);
        $compiler->method('getNodeType')->willReturn('example:node');
        $compiler->method('getNodeVersion')->willReturn(1);
        $this->expectExceptionObject(WorkflowExecutionException::duplicateRegistration('approval node compiler', 'example:node@1'));

        new ApprovalNodeCompilerRegistry([$compiler, $compiler]);
    }

    public function testInvalidVersionIsRejected(): void
    {
        $compiler = static::createStub(ApprovalNodeCompiler::class);
        $compiler->method('getNodeType')->willReturn('example:node');
        $compiler->method('getNodeVersion')->willReturn(0);
        $this->expectExceptionObject(WorkflowException::invalidDocument('/compilation/compiler', 'Compiler identifiers or version are invalid.'));

        new ApprovalNodeCompilerRegistry([$compiler]);
    }
}
