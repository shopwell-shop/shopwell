<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\MessageQueue\fixtures;

use Shopwell\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

/**
 * @internal
 */
class TestTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return self::class;
    }

    public static function getDefaultInterval(): int
    {
        return 1;
    }
}
