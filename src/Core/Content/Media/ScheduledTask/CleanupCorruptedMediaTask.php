<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Media\ScheduledTask;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

/**
 * @codeCoverageIgnore
 *
 * @internal
 */
#[Package('discovery')]
class CleanupCorruptedMediaTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'media.cleanup_corrupted_media';
    }

    public static function getDefaultInterval(): int
    {
        return self::DAILY;
    }
}
