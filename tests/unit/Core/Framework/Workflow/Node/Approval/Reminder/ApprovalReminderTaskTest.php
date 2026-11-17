<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Workflow\Node\Approval\Reminder;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Node\Approval\Reminder\ScheduledTask\ApprovalReminderTask;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ApprovalReminderTask::class)]
class ApprovalReminderTaskTest extends TestCase
{
    public function testRunsEveryMinuteAndReschedulesAfterFailure(): void
    {
        static::assertSame('workflow.approval.reminder', ApprovalReminderTask::getTaskName());
        static::assertSame(60, ApprovalReminderTask::getDefaultInterval());
        static::assertTrue(ApprovalReminderTask::shouldRescheduleOnFailure());
    }
}
