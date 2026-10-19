<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Loyalty\ScheduledTask;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

#[Package('checkout')]
class LoyaltyPointExpirationTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'loyalty_point.expiration';
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
