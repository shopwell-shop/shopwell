<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\ScheduledTask;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

/**
 * Hands the queued notifications of the generic payment gateway to the apps.
 *
 * The notifications are queued instead of being sent while the app waits for the api answer and instead
 * of being sent inside the notification of a channel: a slow app must not slow down a channel, and a
 * broken app must not make a channel believe the platform did not receive anything.
 *
 * @internal
 */
#[Package('framework')]
class DeliverPaymentWebhookTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'payment.webhook.deliver';
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
