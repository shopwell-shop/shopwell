<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Order;

use Psr\Clock\ClockInterface;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\QueryResult;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Webhook\PaymentWebhookDispatcher;

/**
 * The side of the reconciliation that decides and writes.
 *
 * The reconciler asks the interface what happened to a payment, this handler turns the answer into
 * the bookkeeping - the very same paths the notification of the interface would have taken, only the
 * notification never came. It is the counterpart of {@see PaymentOrderPaidHandler}: that one is the
 * write path of a notification, this one the write path of a query.
 *
 * The two are separate on purpose. Asking the interface is a matter of credentials, gateways and
 * retries, deciding what an answer means is the payment order's own life cycle, and only the latter
 * is worth covering with tests of its own - a reconciler that assembles a gateway cannot be tested
 * without one.
 *
 * @internal
 */
#[Package('framework')]
final class PaymentOrderQueryResultHandler
{
    public function __construct(
        private readonly PaymentOrderService $orderService,
        private readonly PaymentWebhookDispatcher $webhookService,
        private readonly PaymentOrderPaidHandler $paidHandler,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * @throws PaymentException
     */
    public function handle(PaymentOrderEntity $order, QueryResult $result): void
    {
        switch ($result->status) {
            case QueryResult::STATUS_PAID:
                $this->applyPaid($order, $result);

                return;
            case QueryResult::STATUS_CLOSED:
                $this->orderService->close($order);
                $this->announceClosed($order, 'closed', PaymentOrderStates::STATE_CLOSED);

                return;
            case QueryResult::STATUS_FAILED:
                $this->orderService->markFailed($order);
                $this->announceClosed($order, 'failed', PaymentOrderStates::STATE_FAILED);

                return;
            case QueryResult::STATUS_ABSENT:
                // the interface does not know the payment: once the order may expire on its own, it
                // does - until then the interface may still open the trade on its side
                if ($order->expireAt === null || $order->expireAt > $this->clock->now()) {
                    return;
                }

                // an order the interface was handed is never expired - the state machine has no
                // transition for it and a late payment would be lost. It is closed instead, which is
                // what the platform can honestly say about an order the interface has no record of.
                if ($this->orderService->isCreated($order)) {
                    $this->orderService->expire($order);
                    $this->announceClosed($order, 'expired', PaymentOrderStates::STATE_EXPIRED);

                    return;
                }

                $this->orderService->close($order);
                $this->announceClosed($order, 'absent', PaymentOrderStates::STATE_CLOSED);

                return;
        }

        // pending: the interface has not settled the payment, nothing is written
    }

    /**
     * The counterpart of the pay notification: the interface reported a final state the app has to
     * know about, so it is told the very same way - only the notification never came, the
     * reconciliation found out by asking.
     */
    private function announceClosed(PaymentOrderEntity $order, string $reason, string $state): void
    {
        $closed = $this->orderService->getById($order->id);

        $this->webhookService->enqueue($closed, PaymentWebhookDispatcher::EVENT_ORDER_CLOSED, [
            'reason' => $reason,
            'state' => $state,
            'closed_at' => $closed->closedAt?->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    /**
     * The amount the interface confirms is compared with the amount of the platform before anything
     * is written - a mismatch is an error here, while a notification records it on itself. The
     * writing and the announcing are the ones of the notification processor.
     *
     * @throws PaymentException
     */
    private function applyPaid(PaymentOrderEntity $order, QueryResult $result): void
    {
        $expected = $order->amount;

        if ($result->amount === null || $result->amount !== $expected) {
            throw PaymentException::amountMismatch($order->paymentNo, $expected, $result->amount ?? 0);
        }

        // the same number in another currency is not the payment of this order, so the currency is
        // judged as strictly as the amount - 100 JPY does not settle an order of 100 CNY. An interface
        // that reports no currency cannot be disproved: rejecting it would leave a payment that really
        // happened unbooked forever, so only a reported currency has to match. The case is ignored
        // because `cny` and `CNY` are the same currency.
        if ($result->currency !== null && strtoupper($result->currency) !== strtoupper($order->currency)) {
            throw PaymentException::currencyMismatch($order->paymentNo, $order->currency, $result->currency);
        }

        $this->paidHandler->handle($order, $result->amount, $result->outPaymentNo, $result->raw);
    }
}
