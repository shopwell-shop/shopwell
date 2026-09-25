<?php declare(strict_types=1);

namespace Shopwell\Core\System\UsageData\ScheduledTask;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

/**
 * @internal
 */
#[Package('data-services')]
class CollectEntityDataTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'usage_data.entity_data.collect';
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
