<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Workflow\Node\Approval\Reminder;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceEntity;
use Shopwell\Core\Framework\Workflow\Node\Approval\Reminder\ApprovalReminderPolicyEvent;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskEntity;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ApprovalReminderPolicyEvent::class)]
class ApprovalReminderPolicyEventTest extends TestCase
{
    public function testPluginsCanDenyAnOtherwiseEligibleReminder(): void
    {
        $event = new ApprovalReminderPolicyEvent(
            new WorkflowTaskEntity(),
            new WorkflowInstanceEntity(),
            'manual',
            Context::createDefaultContext(),
        );
        static::assertTrue($event->isAllowed());
        $event->deny();
        static::assertFalse($event->isAllowed());
    }
}
