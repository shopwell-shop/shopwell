<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\ScheduledTask;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

/**
 * @internal
 */
#[Package('framework')]
class DispatchPaymentEventsTask extends ScheduledTask
{
    private const INTERVAL = 60;

    public static function getTaskName(): string
    {
        return 'payment.events.dispatch';
    }

    public static function getDefaultInterval(): int
    {
        return self::INTERVAL;
    }

    public static function shouldRescheduleOnFailure(): bool
    {
        return true;
    }
}
