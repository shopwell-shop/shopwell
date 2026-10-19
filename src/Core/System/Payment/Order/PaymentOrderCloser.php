<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Order;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\CloseResult;
use Shopwell\Core\System\Payment\Gateway\PaymentCloser;
use Shopwell\Core\System\Payment\PaymentException;

/**
 * Closes a payment order: the channel decides first, the order follows.
 *
 * A locally closed order the payer can still pay would book money into an order the platform
 * considers dead, so the channel is always asked first:
 *
 *  - the channel closed the trade, or does not know it: nothing can be paid on it anymore, the order
 *    is closed locally,
 *  - the trade cannot be closed because it is paid: the payment is booked through the reconciliation
 *    and the order is *not* closed - the caller is told that the close did not happen, because an app
 *    that gets a success would believe the payment is off the table,
 *  - the channel failed: the order stays open, so a later close or the reconciliation can still find
 *    the payment. An order that cannot be closed is better than an order that is believed to be
 *    closed.
 *
 * This is the orchestration around the two services that must not know each other: the reconciliation
 * needs the {@see PaymentOrderService}, so the service itself cannot call the reconciliation.
 *
 * @internal
 */
#[Package('framework')]
final class PaymentOrderCloser
{
    public function __construct(
        private readonly PaymentCloser $paymentCloser,
        private readonly PaymentOrderService $orderService,
        private readonly PaymentOrderReconciler $reconciler,
    ) {
    }

    /**
     * @throws PaymentException when the order cannot be closed, including the case that it is paid
     */
    public function close(PaymentOrderEntity $order): CloseResult
    {
        // the payment is already there: closing would hide it, so the close is refused before the
        // channel is even asked
        if ($order->paidAt !== null) {
            throw PaymentException::orderAlreadyPaid($order->paymentNo);
        }

        if (!$this->orderService->isPayable($order)) {
            // only a closed order is left here: its published error code is kept, because an app may
            // branch on it
            throw PaymentException::orderAlreadyClosed($order->paymentNo);
        }

        $result = $this->paymentCloser->close($order);

        if ($result->status === CloseResult::STATUS_ALREADY_PAID) {
            // the close turned up a payment: book it first - the booking is the correct outcome, the
            // exception only tells the caller that the order was not closed
            $this->reconciler->reconcile($order);

            throw PaymentException::orderAlreadyPaid($order->paymentNo);
        }

        // closed and not found are both closed for the platform: a trade the channel does not know
        // cannot be paid anymore either
        $this->orderService->close($order);

        return $result;
    }
}
