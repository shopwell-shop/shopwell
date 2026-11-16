<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Workflow\Publication\Approval\Node;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Node\Approval\ApprovalConfigurationCompiler;
use Shopwell\Core\Framework\Workflow\Publication\Approval\Node\ApprovalNodeCompilationContext;
use Shopwell\Core\Framework\Workflow\Publication\Approval\Node\ApprovalNodeRole;
use Shopwell\Core\Framework\Workflow\Publication\Approval\Node\TaskApprovalNodeCompiler;
use Shopwell\Tests\Unit\Core\Framework\Workflow\_fixtures\ApprovalRegistryFixture;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(TaskApprovalNodeCompiler::class)]
class TaskApprovalNodeCompilerTest extends TestCase
{
    public function testSeparatesRoutedApprovalFromExecutorCompletedRejection(): void
    {
        $compiler = new TaskApprovalNodeCompiler(new ApprovalConfigurationCompiler(
            ApprovalRegistryFixture::sources(),
            ApprovalRegistryFixture::modes(),
            new ExtensionDispatcher(new EventDispatcher()),
        ), ApprovalRegistryFixture::translator());
        $userId = '0123456789abcdef0123456789abcdef';
        $compiled = $compiler->compile(new ApprovalNodeCompilationContext('review', [
            'label' => 'Finance review',
            'assignee' => ['source' => 'core:user', 'config' => ['userId' => $userId]],
            'approval' => ['mode' => 'core:single', 'config' => []],
        ], '/nodes/1/config'));

        static::assertSame('core:approval', $compiler->getNodeType());
        static::assertSame(1, $compiler->getNodeVersion());
        static::assertSame(ApprovalNodeRole::APPROVAL, $compiler->getRole());
        static::assertSame('Finance review', $compiled->properties['name']);
        static::assertSame([$userId], $compiled->properties['assigneeUserIds']);
        static::assertSame(['approved' => []], $compiled->outputPorts);
        static::assertSame(['rejected'], $compiled->terminalPorts);
    }
}
