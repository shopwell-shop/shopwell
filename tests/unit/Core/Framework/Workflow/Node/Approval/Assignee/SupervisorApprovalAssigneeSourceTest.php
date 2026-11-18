<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Workflow\Node\Approval\Assignee;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Node\Approval\Assignee\SupervisorApprovalAssigneeSource;
use Shopwell\Core\Framework\Workflow\WorkflowException;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SupervisorApprovalAssigneeSource::class)]
class SupervisorApprovalAssigneeSourceTest extends TestCase
{
    public function testCompilesSourceForResolutionAtNodeEntry(): void
    {
        $source = new SupervisorApprovalAssigneeSource();
        $assignment = $source->compile(['level' => 3, 'continuous' => true]);

        static::assertSame('core:supervisors', $source->getName());
        static::assertSame('leader', $assignment->type);
        static::assertTrue($assignment->multiple);
        static::assertSame([], $assignment->knownUserIds);
        static::assertSame(['level' => 3, 'continuous' => true], $assignment->configuration);
    }

    public function testRejectsUnsupportedConfiguration(): void
    {
        $this->expectException(WorkflowException::class);

        (new SupervisorApprovalAssigneeSource())->compile(['unknown' => true]);
    }
}
