<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Payment\Cleanup;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

#[Package('checkout')]
class CleanupPaymentTokenTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'payment_token.cleanup';
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
