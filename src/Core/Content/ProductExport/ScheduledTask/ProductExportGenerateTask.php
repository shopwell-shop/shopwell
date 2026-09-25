<?php declare(strict_types=1);

namespace Shopwell\Core\Content\ProductExport\ScheduledTask;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

#[Package('inventory')]
class ProductExportGenerateTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'product_export_generate_task';
    }

    public static function getDefaultInterval(): int
    {
        return 60;
    }

    public static function shouldRescheduleOnFailure(): bool
    {
        return true;
    }
}
