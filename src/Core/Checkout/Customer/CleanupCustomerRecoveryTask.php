<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Customer;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

#[Package('checkout')]
class CleanupCustomerRecoveryTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'customer.cleanup_customer_recovery';
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
