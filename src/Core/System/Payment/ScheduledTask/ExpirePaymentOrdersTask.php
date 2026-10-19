<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\ScheduledTask;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

/**
 * Expires the payment orders the payer never paid.
 *
 * An order that was never handed to a channel cannot be paid anymore, so it is closed by its own
 * expiry instead of staying payable until the app decides to close it.
 *
 * @internal
 */
#[Package('framework')]
class ExpirePaymentOrdersTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'payment.order.expire';
    }

    public static function getDefaultInterval(): int
    {
        return self::MINUTELY;
    }

    public static function shouldRescheduleOnFailure(): bool
    {
        return true;
    }
}
