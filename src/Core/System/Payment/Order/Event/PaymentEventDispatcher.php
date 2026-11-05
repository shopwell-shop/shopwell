<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Order\Event;

use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Exception\PaymentOrderException;
use Shopwell\Core\System\Payment\Order\Aggregate\PaymentRefund\PaymentRefundCollection;
use Shopwell\Core\System\Payment\Order\Aggregate\PaymentRefund\PaymentRefundEntity;
use Shopwell\Core\System\Payment\Order\PaymentOrderCollection;
use Shopwell\Core\System\Payment\Order\PaymentOrderEntity;
use Shopwell\Core\System\Payment\Order\PaymentOrderStates;
use Shopwell\Core\System\Payment\Order\PaymentRefundStates;
use Shopwell\Core\System\Payment\Webhook\PaymentWebhookDispatcher;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Settlement timestamps persist the work before any subscriber runs. Acknowledgement follows both
 * the internal event and the durable webhook enqueue, so a crash leaves the aggregate retryable.
 * Delivery is at least once: subscribers must deduplicate using the payment or refund id.
 *
 * @internal
 */
#[Package('framework')]
class PaymentEventDispatcher
{
    /**
     * @param EntityRepository<PaymentOrderCollection> $orders
     * @param EntityRepository<PaymentRefundCollection> $refunds
     */
    public function __construct(
        private readonly EntityRepository $orders,
        private readonly EntityRepository $refunds,
        private readonly PaymentWebhookDispatcher $webhooks,
        private readonly EventDispatcherInterface $events,
        private readonly ClockInterface $clock,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function dispatchPaid(PaymentOrderEntity $order): void
    {
        $this->orders->update([[
            'id' => $order->id,
            'eventDispatchAttemptedAt' => $this->clock->now(),
        ]], Context::createDefaultContext());
        $this->events->dispatch(new PaymentOrderPaidEvent($order, Context::createDefaultContext()));
        $this->webhooks->enqueue($order, PaymentWebhookDispatcher::EVENT_ORDER_PAID, [
            'amount' => $order->paidAmount,
            'currency' => $order->currency,
            'provider' => $order->activeAttempt?->providerTechnicalName,
            'channel' => $order->activeAttempt?->channelTechnicalName,
            'out_payment_no' => $order->outPaymentNo,
            'paid_at' => $order->paidAt?->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'state' => PaymentOrderStates::STATE_PAID,
        ]);
        $this->orders->update([[
            'id' => $order->id,
            'eventDispatchedAt' => $this->clock->now(),
        ]], Context::createDefaultContext());
    }

    public function dispatchRefund(PaymentRefundEntity $refund, PaymentOrderEntity $order): void
    {
        $this->refunds->update([[
            'id' => $refund->id,
            'eventDispatchAttemptedAt' => $this->clock->now(),
        ]], Context::createDefaultContext());
        $this->events->dispatch(new PaymentRefundFinishedEvent($refund, $order, Context::createDefaultContext()));
        $this->webhooks->enqueue($order, PaymentWebhookDispatcher::EVENT_REFUND_FINISHED, [
            'refund_no' => $refund->refundNo,
            'out_refund_no' => $refund->outRefundNo,
            'out_refund_id' => $refund->outRefundId,
            'amount' => $refund->amount,
            'currency' => $refund->currency,
            'refunded_amount' => $order->refundedAmount,
            'state' => PaymentRefundStates::STATE_FINISHED,
        ], $refund->id);
        $this->refunds->update([[
            'id' => $refund->id,
            'eventDispatchedAt' => $this->clock->now(),
        ]], Context::createDefaultContext());
    }

    public function dispatchPending(int $limit = 100): int
    {
        $delivered = 0;
        foreach ($this->orders->search($this->pendingCriteria('paidAt', $limit)->addAssociation('activeAttempt'), Context::createDefaultContext())->getEntities() as $order) {
            try {
                $this->dispatchPaid($order);
                ++$delivered;
            } catch (\Throwable $exception) {
                $this->logger->error('Payment event delivery failed.', ['paymentOrderId' => $order->id, 'exception' => $exception]);
            }
        }

        $criteria = $this->pendingCriteria('refundedAt', $limit);
        $criteria->addAssociation('paymentOrder');
        foreach ($this->refunds->search($criteria, Context::createDefaultContext())->getEntities() as $refund) {
            try {
                if ($refund->paymentOrder === null) {
                    throw PaymentOrderException::orderNotFound($refund->paymentOrderId);
                }
                $this->dispatchRefund($refund, $refund->paymentOrder);
                ++$delivered;
            } catch (\Throwable $exception) {
                $this->logger->error('Refund event delivery failed.', ['paymentRefundId' => $refund->id, 'exception' => $exception]);
            }
        }

        return $delivered;
    }

    private function pendingCriteria(string $settledAt, int $limit): Criteria
    {
        $criteria = new Criteria();
        $criteria->addFilter(new NotFilter(NotFilter::CONNECTION_AND, [new EqualsFilter($settledAt, null)]));
        $criteria->addFilter(new EqualsFilter('eventDispatchedAt', null));
        $criteria->addSorting(new FieldSorting('eventDispatchAttemptedAt', FieldSorting::ASCENDING));
        $criteria->addSorting(new FieldSorting('id', FieldSorting::ASCENDING));
        $criteria->setLimit($limit);

        return $criteria;
    }
}
