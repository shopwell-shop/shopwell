<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Workflow\Node\Approval\Assignee;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Node\Approval\Assignee\RoleApprovalAssigneeSource;
use Shopwell\Core\Framework\Workflow\WorkflowException;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(RoleApprovalAssigneeSource::class)]
class RoleApprovalAssigneeSourceTest extends TestCase
{
    public function testCompilesSourceForResolutionAtNodeEntry(): void
    {
        $source = new RoleApprovalAssigneeSource();
        $assignment = $source->compile(['roleIds' => ['ABCDEF0123456789ABCDEF0123456789']]);

        static::assertSame('core:roles', $source->getName());
        static::assertSame('role', $assignment->type);
        static::assertTrue($assignment->multiple);
        static::assertSame([], $assignment->knownUserIds);
        static::assertSame(['abcdef0123456789abcdef0123456789'], $assignment->values);
    }

    public function testRejectsUnsupportedConfiguration(): void
    {
        $this->expectException(WorkflowException::class);

        (new RoleApprovalAssigneeSource())->compile(['unknown' => true]);
    }
}
