<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\ScheduledTask;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

/**
 * Reconciles the payment orders the interfaces never reported back.
 *
 * A notification can be lost or delayed, so the orders that wait for one are asked about at their
 * interface from time to time - see {@see PaymentOrderReconciler}. The interval is shorter than the
 * one of the interfaces' own notifications because the reconciliation is the safety net, not the
 * main path.
 *
 * @internal
 */
#[Package('framework')]
class ReconcileOpenPaymentOrdersTask extends ScheduledTask
{
    /**
     * Five minutes - the base class has no constant for it.
     */
    private const INTERVAL = 300;

    public static function getTaskName(): string
    {
        return 'payment.order.reconcile';
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
