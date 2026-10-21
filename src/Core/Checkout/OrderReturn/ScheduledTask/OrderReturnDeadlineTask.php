<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\OrderReturn\ScheduledTask;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

/**
 * Moves returns that ran out of time.
 *
 * Two deadlines exist and both are handled here: an approval the shop never decided on, and a parcel
 * the customer never announced. Which one is due is told by the state of the return, so one task is
 * enough - a second task would have to read the same rows anyway.
 *
 * @internal
 */
#[Package('after-sales')]
class OrderReturnDeadlineTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'order_return.deadline';
    }

    public static function getDefaultInterval(): int
    {
        return self::MINUTELY * 15;
    }

    public static function shouldRescheduleOnFailure(): bool
    {
        return true;
    }
}
