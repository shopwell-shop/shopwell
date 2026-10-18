<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Payment\Gateway;

use Psr\Log\LoggerInterface;
use Shopwell\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Order\Event\PaymentOrderPaidEvent;
use Shopwell\Core\System\StateMachine\Exception\IllegalTransitionException;

/**
 * Writes a paid payment order back to the checkout transaction it was opened for.
 *
 * The payment order is the source of truth of the payment, the order transaction is where the rest of
 * the checkout sees it. The notification of an interface and the reconciliation of a missed
 * notification both announce the payment, so this listener runs for every way a payment can arrive
 * and needs no retry bookkeeping of its own.
 *
 * @internal
 */
#[Package('checkout')]
final class OrderPaymentPaidListener
{
    public function __construct(
        private readonly OrderTransactionStateHandler $stateHandler,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(PaymentOrderPaidEvent $event): void
    {
        $paymentOrder = $event->getPaymentOrder();
        $orderTransactionId = $paymentOrder->orderTransactionId;

        // a payment of an external app has no checkout order: the app carries the payment itself and
        // there is no transaction to write the state to
        if ($orderTransactionId === null) {
            return;
        }

        try {
            $this->stateHandler->paid($orderTransactionId, $event->getContext());
        } catch (IllegalTransitionException $exception) {
            // The announcement runs before the webhook is queued. A listener that throws makes the
            // notification processor judge the notification as unprocessed and retry it forever - the
            // money is booked on our side but the app never hears about it. A refused transition is a
            // business conflict (the order was cancelled, the transaction already failed) that a retry
            // cannot resolve, so it is logged and left for a human; a database or connection error is
            // transient and must be thrown so the retry mechanism can do its job.
            $this->logger->error('The paid payment order could not be written back to its order transaction.', [
                'paymentNo' => $paymentOrder->paymentNo,
                'orderTransactionId' => $orderTransactionId,
                'currentState' => $exception->getParameter('currentState'),
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
