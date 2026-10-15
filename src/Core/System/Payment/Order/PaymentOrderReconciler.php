<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Order;

use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\Gateway\PaymentQuery;
use Shopwell\Core\System\Payment\Gateway\QueryResult;
use Shopwell\Core\System\Payment\Order\Aggregate\PaymentTransaction\PaymentTransactionEntity;
use Shopwell\Core\System\Payment\Order\Event\PaymentOrderPaidEvent;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\PaymentConfigResolver;
use Shopwell\Core\System\Payment\Webhook\PaymentWebhookDispatcher;
use Shopwell\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateCollection;
use Shopwell\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopwell\Core\System\StateMachine\StateMachineException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Reconciles the payment orders the interfaces never reported back.
 *
 * A notification can be lost or delayed, so the platform asks the interface itself about every order
 * that waits for one: the answer says what happens with the order - a payment the interface confirms
 * goes through the very same pay path as a notification does, a payment the interface gave up on is
 * closed or failed, and a payment the interface does not know expires once it may. Everything the
 * interface has not settled yet is left alone, the next run asks again.
 *
 * The orders are found through their state alone - `opened` and neither paid nor closed - so no
 * bookkeeping of the queries is needed and no order is asked about forever: an order stays `opened`
 * only as long as the interface may still be settling it, and a missing notification never changes
 * the state.
 *
 * Processing is idempotent: a payment that arrived between the search and the question changes
 * nothing, because the notification processor stays the authority and the reconciliation only fills
 * its gaps.
 *
 * @internal
 */
#[Package('framework')]
final class PaymentOrderReconciler
{
    /**
     * A payment order younger than this is left alone: the payer may still be on the page of the
     * interface, and asking about a payment while it happens only produces noise.
     */
    final public const MIN_ORDER_AGE = 600;

    /**
     * @param EntityRepository<PaymentOrderCollection> $paymentOrderRepository
     * @param EntityRepository<StateMachineStateCollection> $stateRepository
     */
    public function __construct(
        private readonly EntityRepository $paymentOrderRepository,
        private readonly EntityRepository $stateRepository,
        private readonly PaymentConfigResolver $configResolver,
        private readonly PaymentOrderService $orderService,
        private readonly PaymentWebhookDispatcher $webhookService,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ClockInterface $clock,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Asks every due payment order about its payment at the interface.
     *
     * @return int the number of payment orders that were asked about
     */
    public function reconcileDue(int $limit = 100): int
    {
        $criteria = new Criteria();
        // only the orders that wait for the interface: `opened` is the one state an order is in while
        // the interface may still be settling its payment - a failed order needs no reconciliation
        // even though it is neither paid nor closed, which is why the state and not the timestamps
        // alone picks the orders
        $criteria->addFilter(new EqualsFilter('stateId', $this->openedStateId()));
        $criteria->addFilter(new EqualsFilter('paidAt', null));
        $criteria->addFilter(new EqualsFilter('closedAt', null));
        // a date range filter compares strings, so the clock is formatted the way the storage does
        $criteria->addFilter(new RangeFilter('createdAt', [
            RangeFilter::LTE => $this->clock->now()
                ->modify(\sprintf('-%d seconds', self::MIN_ORDER_AGE))
                ->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]));
        $criteria->addAssociation('paymentApp');
        $criteria->addAssociation('transactions');
        $criteria->setLimit($limit);

        $orders = $this->paymentOrderRepository->search($criteria, Context::createDefaultContext())->getEntities();

        $asked = 0;
        foreach ($orders as $order) {
            if (!$order instanceof PaymentOrderEntity) {
                continue;
            }

            try {
                $this->reconcile($order);
            } catch (\Throwable $exception) {
                // one order the interface cannot be asked about must not keep the others from being
                // asked - the next run tries this one again
                $this->logger->warning('Reconciling the payment order with the channel failed.', [
                    'paymentNo' => $order->paymentNo,
                    'provider' => $order->providerTechnicalName,
                    'channel' => $order->channelTechnicalName,
                    'error' => $exception->getMessage(),
                ]);

                continue;
            }

            ++$asked;
        }

        return $asked;
    }

    /**
     * Asks one payment order about its payment at the interface and applies the answer.
     *
     * @throws PaymentException when the interface cannot be asked or its answer cannot be applied
     */
    public function reconcile(PaymentOrderEntity $order): void
    {
        // a payment may have arrived between the search and this very question - the notification
        // processor is the authority, the reconciliation only fills its gaps
        if (!$this->orderService->isPayable($order)) {
            return;
        }

        $app = $order->paymentApp;

        if (!$app instanceof PaymentAppEntity) {
            throw PaymentException::orderNotFound($order->paymentNo);
        }

        $resolved = $this->configResolver->resolve(
            $app,
            $order->channelTechnicalName,
            $order->currency,
            $order->device,
            $order->channelParams ?? []
        );

        $model = PaymentGatewayModel::forQuery($order, $this->lastChannelResponseOf($order));
        $gateway = $resolved->provider->gatewayFactory->createForPayment($resolved, (string) $order->notifyUrl, (string) $order->returnUrl);

        $gateway->execute(new PaymentQuery($model));
        $result = $model->result(QueryResult::class, $order->channelTechnicalName);

        $this->apply($order, $result);
    }

    /**
     * @throws PaymentException
     */
    private function apply(PaymentOrderEntity $order, QueryResult $result): void
    {
        switch ($result->status) {
            case QueryResult::STATUS_PAID:
                $this->applyPaid($order, $result);

                return;
            case QueryResult::STATUS_CLOSED:
                $this->orderService->close($order);
                $this->applyClosed($order, 'closed', PaymentOrderStates::STATE_CLOSED);

                return;
            case QueryResult::STATUS_FAILED:
                $this->orderService->markFailed($order);
                $this->applyClosed($order, 'failed', PaymentOrderStates::STATE_FAILED);

                return;
            case QueryResult::STATUS_ABSENT:
                // the interface does not know the payment: once the order may expire on its own, it
                // does - until then the interface may still open the trade on its side
                if ($order->expireAt !== null && $order->expireAt <= $this->clock->now()) {
                    $this->orderService->expire($order);
                    $this->applyClosed($order, 'expired', PaymentOrderStates::STATE_EXPIRED);
                }

                return;
        }

        // pending: the interface has not settled the payment, nothing is written
    }

    /**
     * The counterpart of the pay notification: the interface reported a final state the app has to
     * know about, so it is told the very same way - only the notification never came, the
     * reconciliation found out by asking.
     */
    private function applyClosed(PaymentOrderEntity $order, string $reason, string $state): void
    {
        $closed = $this->orderService->getById($order->id);

        $this->webhookService->enqueue($closed, PaymentWebhookDispatcher::EVENT_ORDER_CLOSED, [
            'reason' => $reason,
            'state' => $state,
            'closed_at' => $closed->closedAt?->format(\DateTimeInterface::ATOM),
        ]);
    }

    /**
     * The pay path of the notification processor, word for word: the amount the interface confirms is
     * compared with the amount of the platform before anything is written, and the app is notified
     * exactly like it is by a notification.
     *
     * @throws PaymentException
     */
    private function applyPaid(PaymentOrderEntity $order, QueryResult $result): void
    {
        $expected = $order->amount;

        if ($result->amount === null || $result->amount !== $expected) {
            throw PaymentException::amountMismatch($order->paymentNo, $expected, $result->amount ?? 0);
        }

        // an order may be paid without the app ever having asked for the pay parameters
        $this->orderService->markOpened($order);
        $this->orderService->markPaid($order, $result->amount ?? $order->amount, $result->outPaymentNo, $result->raw);

        $paid = $this->orderService->getById($order->id);

        $this->eventDispatcher->dispatch(new PaymentOrderPaidEvent($paid, Context::createDefaultContext()));

        $this->webhookService->enqueue($paid, PaymentWebhookDispatcher::EVENT_ORDER_PAID, [
            'amount' => $paid->paidAmount,
            'currency' => $paid->currency,
            'provider' => $paid->providerTechnicalName,
            'channel' => $paid->channelTechnicalName,
            'out_payment_no' => $paid->outPaymentNo,
            'paid_at' => $paid->paidAt?->format(\DateTimeInterface::ATOM),
            'state' => PaymentOrderStates::STATE_PAID,
        ]);
    }

    /**
     * The answer of the last capture, which is where the interfaces that hand out their own ids for
     * a payment are found again, e.g. the order id of paypal or the checkout session of stripe.
     *
     * @return array<string, mixed>
     */
    private function lastChannelResponseOf(PaymentOrderEntity $order): array
    {
        foreach (array_reverse($order->transactions ?? []) as $transaction) {
            if (!$transaction instanceof PaymentTransactionEntity || $transaction->type !== PaymentTransactionEntity::TYPE_CAPTURE) {
                continue;
            }

            $response = $transaction->responsePayload;

            return \is_array($response) ? $response : [];
        }

        return [];
    }

    private function openedStateId(): string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('stateMachine.technicalName', PaymentOrderStates::STATE_MACHINE));
        $criteria->addFilter(new EqualsFilter('technicalName', PaymentOrderStates::STATE_OPENED));
        $criteria->setLimit(1);

        $state = $this->stateRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();

        if (!$state instanceof StateMachineStateEntity) {
            throw StateMachineException::stateMachineStateNotFound(PaymentOrderStates::STATE_MACHINE, PaymentOrderStates::STATE_OPENED);
        }

        return $state->getId();
    }
}
