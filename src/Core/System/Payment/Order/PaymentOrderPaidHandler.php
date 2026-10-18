<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Order;

use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Order\Event\PaymentOrderPaidEvent;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Webhook\PaymentWebhookDispatcher;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * The one path a payment order becomes paid.
 *
 * A payment that arrived is written here and nowhere else: the order is opened if the app never
 * asked for the pay parameters, it is marked paid, the platform announces it and the app is told.
 * Both the notification of an interface and the reconciliation that asks the interface go through
 * here, so the two can never drift apart.
 *
 * What stays with the callers is what is genuinely theirs: the notification reports a rejected
 * payment as a result on the notification, the reconciliation raises it as an error, and each of
 * them decides on its own when a payment is a duplicate. Only the writing and the announcing are
 * shared.
 *
 * @internal
 */
#[Package('framework')]
final class PaymentOrderPaidHandler
{
    public function __construct(
        private readonly PaymentOrderService $orderService,
        private readonly PaymentWebhookDispatcher $webhookService,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @param array<string, mixed>|null $channelData the answer of the interface that proves the payment
     *
     * @throws PaymentException
     */
    public function handle(PaymentOrderEntity $order, int $paidAmount, ?string $outPaymentNo, ?array $channelData): PaymentOrderEntity
    {
        // an order may be paid without the app ever having asked for the pay parameters, and the
        // state machine only allows a payment from `opened`
        $this->orderService->markOpened($order);
        $this->orderService->markPaid($order, $paidAmount, $outPaymentNo, $channelData);

        return $this->announce($order);
    }

    /**
     * Announces a payment that is already written: the platform learns about it and the app is told.
     *
     * Separate from handle() because a payment may already be written while one of its consequences
     * failed - the notification then arrives again and again, and each of those duplicates is a free
     * retry window for whatever did not happen the first time.
     *
     * Nothing is judged and nothing is written here, the state decision stays with the caller.
     *
     * @throws PaymentException
     */
    public function announce(PaymentOrderEntity $order): PaymentOrderEntity
    {
        $paid = $this->orderService->getById($order->id);

        $this->eventDispatcher->dispatch(new PaymentOrderPaidEvent($paid, Context::createDefaultContext()));

        $this->webhookService->enqueue($paid, PaymentWebhookDispatcher::EVENT_ORDER_PAID, [
            'amount' => $paid->paidAmount,
            'currency' => $paid->currency,
            'provider' => $paid->providerTechnicalName,
            'channel' => $paid->channelTechnicalName,
            'out_payment_no' => $paid->outPaymentNo,
            'paid_at' => $paid->paidAt?->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'state' => PaymentOrderStates::STATE_PAID,
        ]);

        return $paid;
    }
}
