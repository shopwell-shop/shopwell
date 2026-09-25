<?php

declare(strict_types=1);

namespace Shopwell\Storefront\Theme\ScheduledTask;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('discovery')]
final class DeleteThemeFilesTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'theme.delete_files';
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
