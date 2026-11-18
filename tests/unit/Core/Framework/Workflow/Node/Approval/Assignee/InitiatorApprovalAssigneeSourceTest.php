<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Workflow\Node\Approval\Assignee;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Node\Approval\Assignee\InitiatorApprovalAssigneeSource;
use Shopwell\Core\Framework\Workflow\WorkflowException;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(InitiatorApprovalAssigneeSource::class)]
class InitiatorApprovalAssigneeSourceTest extends TestCase
{
    public function testCompilesSourceForResolutionAtNodeEntry(): void
    {
        $source = new InitiatorApprovalAssigneeSource();
        $assignment = $source->compile([]);

        static::assertSame('core:initiator', $source->getName());
        static::assertSame('initiator', $assignment->type);
        static::assertFalse($assignment->multiple);
        static::assertSame([], $assignment->knownUserIds);
    }

    public function testRejectsUnsupportedConfiguration(): void
    {
        $this->expectException(WorkflowException::class);

        (new InitiatorApprovalAssigneeSource())->compile(['unknown' => true]);
    }
}
