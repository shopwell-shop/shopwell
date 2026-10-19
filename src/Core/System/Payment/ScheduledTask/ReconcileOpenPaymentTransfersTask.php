<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\ScheduledTask;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

/**
 * Asks the interfaces what became of the payouts the platform is still waiting for.
 *
 * A submission may answer nothing at all, and a notification can be lost - so a payout that is on its
 * way is asked about from time to time, see {@see \Shopwell\Core\System\Payment\Transfer\PaymentTransferReconciler}.
 *
 * Half an hour and not five minutes like the orders: a payout is a business transaction the interfaces
 * settle in hours, not seconds, and every query is a signed call against a rate limited interface. The
 * questions are idempotent, so a slower cadence costs nothing but latency of the record - while asking
 * a hundred payouts every five minutes would only produce the same `processing` hundred times.
 *
 * @internal
 */
#[Package('framework')]
class ReconcileOpenPaymentTransfersTask extends ScheduledTask
{
    /**
     * Half an hour - the base class has no constant for it.
     */
    private const INTERVAL = 1800;

    public static function getTaskName(): string
    {
        return 'payment.transfer.reconcile';
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
