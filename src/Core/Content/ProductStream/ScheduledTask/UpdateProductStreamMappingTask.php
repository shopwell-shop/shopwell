<?php declare(strict_types=1);

namespace Shopwell\Core\Content\ProductStream\ScheduledTask;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

#[Package('inventory')]
class UpdateProductStreamMappingTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'product_stream.mapping.update';
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
