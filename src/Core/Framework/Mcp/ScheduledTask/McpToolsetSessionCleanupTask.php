<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Mcp\ScheduledTask;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

/**
 * @experimental stableVersion:v6.8.0
 */
#[Package('framework')]
class McpToolsetSessionCleanupTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'mcp_toolset_session.cleanup';
    }

    public static function getDefaultInterval(): int
    {
        return self::DAILY;
    }

    public static function shouldRescheduleOnFailure(): bool
    {
        return true;
    }
}
