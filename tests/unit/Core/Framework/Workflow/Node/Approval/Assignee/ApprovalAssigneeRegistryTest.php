<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Workflow\Node\Approval\Assignee;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;
use Shopwell\Core\Framework\Workflow\Node\Approval\Assignee\ApprovalAssigneeRegistry;
use Shopwell\Core\Framework\Workflow\Node\Approval\Assignee\DepartmentLeaderApprovalAssigneeSource;
use Shopwell\Core\Framework\Workflow\WorkflowException;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ApprovalAssigneeRegistry::class)]
class ApprovalAssigneeRegistryTest extends TestCase
{
    public function testResolvesTheRegisteredSource(): void
    {
        $source = new DepartmentLeaderApprovalAssigneeSource();
        $registry = new ApprovalAssigneeRegistry([$source]);

        static::assertSame($source, $registry->get($source->getName()));
    }

    public function testRejectsDuplicateSourcesInsteadOfChangingThePublishedMeaning(): void
    {
        $source = new DepartmentLeaderApprovalAssigneeSource();
        $this->expectExceptionObject(WorkflowExecutionException::duplicateRegistration('approval assignee source', $source->getName()));

        new ApprovalAssigneeRegistry([$source, new DepartmentLeaderApprovalAssigneeSource()]);
    }

    public function testRejectsAnUninstalledSource(): void
    {
        $registry = new ApprovalAssigneeRegistry([]);
        $this->expectException(WorkflowException::class);

        $registry->get('example:uninstalled');
    }
}
