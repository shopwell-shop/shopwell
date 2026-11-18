<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Workflow\Publication\Approval\Node;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Node\Approval\ApprovalConfigurationCompiler;
use Shopwell\Core\Framework\Workflow\Node\Approval\ApprovalTaskPolicy;
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
        ), ApprovalRegistryFixture::translator(), new ApprovalTaskPolicy(new EventDispatcher()));
        $userId = '0123456789abcdef0123456789abcdef';
        $compiled = $compiler->compile(new ApprovalNodeCompilationContext('review', [
            'label' => 'Finance review',
            'automation' => ['approveInitiator' => true],
            'missingAssignee' => ['action' => 'assign', 'userId' => 'abcdef0123456789abcdef0123456789'],
            'assignee' => ['source' => 'core:user', 'config' => ['userId' => $userId]],
            'approval' => ['mode' => 'core:single', 'config' => []],
        ], '/nodes/1/config'));

        static::assertSame('core:approval', $compiler->getNodeType());
        static::assertSame(1, $compiler->getNodeVersion());
        static::assertSame(ApprovalNodeRole::APPROVAL, $compiler->getRole());
        static::assertSame('Finance review', $compiled->properties['name']);
        static::assertSame(['decision' => 'manual', 'approveInitiator' => true, 'deduplication' => 'none'], $compiled->properties['approvalAutomation']);
        static::assertSame([$userId, 'abcdef0123456789abcdef0123456789'], $compiled->properties['assigneeUserIds']);
        static::assertSame(['action' => 'assign', 'userId' => 'abcdef0123456789abcdef0123456789'], $compiled->properties['missingAssignee']);
        static::assertSame(['approved' => []], $compiled->outputPorts);
        static::assertSame(['rejected'], $compiled->terminalPorts);
    }

    public function testFreezesAnExplicitlyEmptyOperationList(): void
    {
        $compiler = new TaskApprovalNodeCompiler(new ApprovalConfigurationCompiler(
            ApprovalRegistryFixture::sources(),
            ApprovalRegistryFixture::modes(),
            new ExtensionDispatcher(new EventDispatcher()),
        ), ApprovalRegistryFixture::translator(), new ApprovalTaskPolicy(new EventDispatcher()));
        $compiled = $compiler->compile(new ApprovalNodeCompilationContext('review', [
            'assignee' => ['source' => 'core:user', 'config' => ['userId' => '0123456789abcdef0123456789abcdef']],
            'approval' => ['mode' => 'core:single', 'config' => []],
            'operations' => [],
        ], '/nodes/1/config'));

        static::assertSame([], $compiled->properties['approvalOperations']);
    }

    public function testFreezesTheApprovalDeadline(): void
    {
        $compiler = new TaskApprovalNodeCompiler(new ApprovalConfigurationCompiler(
            ApprovalRegistryFixture::sources(),
            ApprovalRegistryFixture::modes(),
            new ExtensionDispatcher(new EventDispatcher()),
        ), ApprovalRegistryFixture::translator(), new ApprovalTaskPolicy(new EventDispatcher()));
        $compiled = $compiler->compile(new ApprovalNodeCompilationContext('review', [
            'assignee' => ['source' => 'core:user', 'config' => ['userId' => '0123456789abcdef0123456789abcdef']],
            'approval' => ['mode' => 'core:single', 'config' => []],
            'deadline' => ['minutes' => 60, 'remind' => false],
        ], '/nodes/1/config'));
        static::assertSame(['minutes' => 60, 'remind' => false], $compiled->properties['approvalDeadline']);
    }

    public function testFreezesFormPermissionsAgainstThePublishedSchema(): void
    {
        $compiler = new TaskApprovalNodeCompiler(new ApprovalConfigurationCompiler(
            ApprovalRegistryFixture::sources(),
            ApprovalRegistryFixture::modes(),
            new ExtensionDispatcher(new EventDispatcher()),
        ), ApprovalRegistryFixture::translator(), new ApprovalTaskPolicy(new EventDispatcher()));
        $permissions = ['note' => ['access' => 'editable', 'required' => true]];
        $compiled = $compiler->compile(new ApprovalNodeCompilationContext('review', [
            'assignee' => ['source' => 'core:user', 'config' => ['userId' => '0123456789abcdef0123456789abcdef']],
            'approval' => ['mode' => 'core:single', 'config' => []], 'formPermissions' => $permissions,
        ], '/nodes/1/config', [['key' => 'note', 'label' => 'Review note', 'type' => 'text', 'required' => false]]));
        static::assertSame($permissions, $compiled->properties['approvalFormPermissions']);
    }

    public function testUnknownFormFieldReportsANodeConfigurationDiagnostic(): void
    {
        $compiler = new TaskApprovalNodeCompiler(new ApprovalConfigurationCompiler(
            ApprovalRegistryFixture::sources(),
            ApprovalRegistryFixture::modes(),
            new ExtensionDispatcher(new EventDispatcher()),
        ), ApprovalRegistryFixture::translator(), new ApprovalTaskPolicy(new EventDispatcher()));
        $context = new ApprovalNodeCompilationContext('review', ['formPermissions' => ['unknown' => ['access' => 'hidden']]], '/nodes/1/config');
        $this->expectExceptionObject($context->reject('approval_invalid_form_permissions', '/formPermissions'));
        $compiler->compile($context);
    }
}
