<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Workflow\Node\Approval\Assignee;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Node\Approval\Assignee\DepartmentLeaderApprovalAssigneeSource;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(DepartmentLeaderApprovalAssigneeSource::class)]
class DepartmentLeaderApprovalAssigneeSourceTest extends TestCase
{
    public function testDefersOrganizationMembershipUntilNodeEntry(): void
    {
        $source = new DepartmentLeaderApprovalAssigneeSource();
        $assignment = $source->compile([]);

        static::assertSame('core:department-leaders', $source->getName());
        static::assertSame('unitLeader', $assignment->type);
        static::assertTrue($assignment->multiple);
        static::assertSame([], $assignment->values);
        static::assertSame([], $assignment->knownUserIds);
    }

    public function testCompilesAncestorSelection(): void
    {
        $assignment = (new DepartmentLeaderApprovalAssigneeSource())->compile(['level' => 2]);

        static::assertSame(['level' => 2, 'continuous' => false], $assignment->configuration);
    }
}
