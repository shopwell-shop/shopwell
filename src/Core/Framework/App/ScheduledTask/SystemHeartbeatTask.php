<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\ScheduledTask;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
class SystemHeartbeatTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'app.system_heartbeat';
    }

    public static function getDefaultInterval(): int
    {
        return self::WEEKLY;
    }

    public static function shouldRescheduleOnFailure(): bool
    {
        return true;
    }
}
