<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Node\Approval\Reminder\ScheduledTask;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

/**
 * @internal
 */
#[Package('framework')]
class ApprovalReminderTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'workflow.approval.reminder';
    }

    public static function getDefaultInterval(): int
    {
        return self::MINUTELY;
    }

    public static function shouldRescheduleOnFailure(): bool
    {
        return true;
    }
}
