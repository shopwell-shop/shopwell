<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Workflow\Publication\Approval\Node;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Publication\Approval\Node\ApprovalNodeCompilationContext;
use Shopwell\Core\Framework\Workflow\Publication\Approval\Node\ApprovalNodeRole;
use Shopwell\Core\Framework\Workflow\Publication\Approval\Node\CopyApprovalNodeCompiler;
use Shopwell\Core\Framework\Workflow\WorkflowException;
use Shopwell\Tests\Unit\Core\Framework\Workflow\_fixtures\ApprovalRegistryFixture;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CopyApprovalNodeCompiler::class)]
class CopyApprovalNodeCompilerTest extends TestCase
{
    public function testCompilesRecipientsWithoutCreatingAnApprovalStage(): void
    {
        $compiler = new CopyApprovalNodeCompiler(ApprovalRegistryFixture::translator());
        $userId = '0123456789abcdef0123456789abcdef';
        $compiled = $compiler->compile(new ApprovalNodeCompilationContext('copy', ['userIds' => [$userId]], '/nodes/1/config'));

        static::assertSame('core:copy', $compiler->getNodeType());
        static::assertSame(1, $compiler->getNodeVersion());
        static::assertSame(ApprovalNodeRole::ACTIVITY, $compiler->getRole());
        static::assertSame('snaker:copy', $compiled->type);
        static::assertSame(['name' => 'Copy recipients', 'description' => '', 'userIdList' => [$userId], 'approvalFormPermissions' => []], $compiled->properties);
        static::assertSame(['out' => []], $compiled->outputPorts);
        static::assertSame([], $compiled->terminalPorts);
    }

    /**
     * @param array<string, mixed> $configuration
     */
    #[DataProvider('invalidRecipients')]
    public function testRejectsInvalidRecipients(array $configuration): void
    {
        $compiler = new CopyApprovalNodeCompiler(ApprovalRegistryFixture::translator());
        $this->expectException(WorkflowException::class);
        $compiler->compile(new ApprovalNodeCompilationContext('copy', $configuration, '/nodes/1/config'));
    }

    public static function invalidRecipients(): iterable
    {
        yield 'missing' => [[]];
        yield 'empty' => [['userIds' => []]];
        yield 'invalid identifier' => [['userIds' => ['invalid']]];
        yield 'non-string identifier' => [['userIds' => [123]]];
        yield 'duplicate' => [['userIds' => ['0123456789abcdef0123456789abcdef', '0123456789ABCDEF0123456789ABCDEF']]];
        yield 'unknown configuration' => [['userIds' => ['0123456789abcdef0123456789abcdef'], 'approval' => []]];
    }
}
