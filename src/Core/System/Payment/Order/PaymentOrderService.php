<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Order;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Psr\Clock\ClockInterface;
use Shopwell\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopwell\Core\Checkout\Order\OrderEntity;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Payment\Api\Struct\PaymentOrderRequest;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\PaymentConfigResolver;
use Shopwell\Core\System\StateMachine\Loader\InitialStateIdLoader;

/**
 * Creates, reads and closes payment orders.
 *
 * Idempotency is guaranteed by the unique index on (payment_app_id, out_trade_no): sending the same
 * out trade number twice returns the very same payment order instead of creating a second one.
 *
 * @internal
 */
#[Package('framework')]
final class PaymentOrderService
{
    final public const DEFAULT_EXPIRE_MINUTES = 30;

    /**
     * @param EntityRepository<PaymentOrderCollection> $paymentOrderRepository
     */
    public function __construct(
        private readonly EntityRepository $paymentOrderRepository,
        private readonly PaymentConfigResolver $configResolver,
        private readonly InitialStateIdLoader $initialStateIdLoader,
        private readonly PaymentOrderStateHandler $stateHandler,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * The order and its transaction are the references of the self-operated checkout: its checkout
     * hands them over so a payment can be found from the order it belongs to. They are objects rather
     * than ids on purpose - the payment api has no business letting an external app claim that a
     * payment belongs to an order of the shop.
     *
     * @throws PaymentException
     */
    public function create(
        PaymentAppEntity $app,
        PaymentOrderRequest $request,
        ?OrderEntity $order = null,
        ?OrderTransactionEntity $orderTransaction = null,
    ): PaymentOrderEntity {
        // validates that the channel exists, is enabled for the app, that the app is authorized for its
        // interface, that both support the currency and the device, and that the runtime parameters the
        // channel demands are all there
        $resolved = $this->configResolver->resolve(
            $app,
            $request->channel,
            $request->currency,
            $request->device,
            $request->channelParams
        );

        $existing = $this->findByOutTradeNo($app->id, $request->outTradeNo);
        if ($existing !== null) {
            return $existing;
        }

        $now = $this->clock->now();
        $id = Uuid::randomHex();
        $expireAt = $now->modify(\sprintf('+%d minutes', $request->expireMinutes ?? self::DEFAULT_EXPIRE_MINUTES));

        $row = [
            'id' => $id,
            'paymentNo' => $this->generatePaymentNo($now),
            'outTradeNo' => $request->outTradeNo,
            'paymentAppId' => $app->id,
            'providerTechnicalName' => $resolved->provider->technicalName,
            'channelTechnicalName' => $request->channel,
            'channelParams' => $request->channelParams === [] ? null : $request->channelParams,
            'amount' => $request->amount,
            // a fresh order was neither paid nor refunded; the fields are not nullable, so the
            // dal write validation demands them
            'paidAmount' => 0,
            'refundedAmount' => 0,
            'currency' => $request->currency,
            'subject' => $request->subject,
            'body' => $request->body,
            'attach' => $request->attach,
            'stateId' => $this->initialStateIdLoader->get(PaymentOrderStates::STATE_MACHINE),
            'clientIp' => $request->clientIp,
            'device' => $request->device,
            'notifyUrl' => $request->notifyUrl ?? $app->notifyUrl,
            'returnUrl' => $request->returnUrl ?? $app->returnUrl,
            'idempotencyKey' => $request->idempotencyKey,
            'expireAt' => $expireAt,
        ];

        // only the self-operated checkout references its own order - an external app never does, and
        // the keys are left out instead of written as null so the columns stay empty. The order and
        // its transaction are versioned, so the reference spans id and version: the foreign keys are
        // composite, and the version field is required as soon as the id is written.
        if ($order instanceof OrderEntity) {
            $row['orderId'] = $order->getId();
            // an entity that was not hydrated from the database carries no version; the checkout
            // always works on the live version, which is the same value the dal would default to
            $row['orderVersionId'] = $order->getVersionId() ?? Defaults::LIVE_VERSION;
        }

        if ($orderTransaction instanceof OrderTransactionEntity) {
            $row['orderTransactionId'] = $orderTransaction->getId();
            $row['orderTransactionVersionId'] = $orderTransaction->getVersionId() ?? Defaults::LIVE_VERSION;
        }

        try {
            $this->paymentOrderRepository->create([$row], Context::createDefaultContext());
        } catch (UniqueConstraintViolationException) {
            // two unique keys describe a create: the out trade number of the app and its idempotency
            // key. A replay with the same idempotency key but a fresh trade number violates the
            // second one, so the lookup by trade number alone would miss the order it created.
            $race = $this->findByOutTradeNo($app->id, $request->outTradeNo);

            if ($race === null && $request->idempotencyKey !== null) {
                $race = $this->findByIdempotencyKey($app->id, $request->idempotencyKey);
            }

            if ($race === null) {
                // the conflicting row is not readable in this request, so its payment number is not
                // known either - reporting the trade number is the only honest answer
                throw PaymentException::orderConflict($request->outTradeNo);
            }

            return $race;
        }

        return $this->getById($id);
    }

    /**
     * The payment order of a checkout transaction - the newest one, if the checkout tried more than
     * once.
     *
     * There is no unique constraint on the transaction: reopening a payment after the first one timed
     * out or failed is normal business and has to create a second payment order. The newest one is the
     * one in charge, because only it may still be within its expiry.
     */
    public function findLatestByOrderTransaction(string $orderTransactionId): ?PaymentOrderEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderTransactionId', $orderTransactionId));
        // the caller resolves the channel credentials of the payment, which needs the app
        $criteria->addAssociation('paymentApp');
        // a caller reopening the payment rebuilds the parameters of the hand over from the answer of
        // the last capture, which lives on the transactions - without them it would believe the
        // payment never reached the channel and open a second trade
        $criteria->addAssociation('transactions');
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING));
        $criteria->setLimit(1);

        $order = $this->paymentOrderRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();

        return $order instanceof PaymentOrderEntity ? $order : null;
    }

    /**
     * @throws PaymentException
     */
    public function getByPaymentNo(string $paymentNo, PaymentAppEntity $app): PaymentOrderEntity
    {
        $order = $this->findByPaymentNo($paymentNo);

        if (!$order instanceof PaymentOrderEntity) {
            throw PaymentException::orderNotFound($paymentNo);
        }

        if ($order->paymentAppId !== $app->id) {
            throw PaymentException::foreignApp($app->technicalName, $paymentNo);
        }

        return $order;
    }

    /**
     * Looks a payment order up without knowing the app, which is what an incoming channel
     * notification needs: the notification carries the payment number, the app is derived from it.
     * The app is loaded as an association, so the caller can resolve the channel credentials.
     */
    public function findByPaymentNo(string $paymentNo): ?PaymentOrderEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('paymentNo', $paymentNo));
        $criteria->addAssociation('paymentApp');
        $criteria->setLimit(1);

        $order = $this->paymentOrderRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();

        return $order instanceof PaymentOrderEntity ? $order : null;
    }

    /**
     * Closes the order locally. The channel is asked first - see {@see PaymentOrderCloser}, which is
     * the only caller that belongs here: this service writes the state machine and never talks to a
     * channel, so closing at the channel cannot be forgotten half way.
     *
     * @throws PaymentException
     */
    public function close(PaymentOrderEntity $order): void
    {
        // "paid" and "closed" are two different answers for the app: a paid order means the money has
        // to be booked, a closed one only means the close was repeated
        if ($order->paidAt !== null) {
            throw PaymentException::orderAlreadyPaid($order->paymentNo);
        }

        if ($order->closedAt !== null) {
            throw PaymentException::orderAlreadyClosed($order->paymentNo);
        }

        $this->stateHandler->close($order->id);

        $this->paymentOrderRepository->update([[
            'id' => $order->id,
            'closedAt' => $this->clock->now(),
        ]], Context::createDefaultContext());
    }

    /**
     * Expires a single payment order the payer never paid, e.g. one the interface no longer knows -
     * see {@see PaymentOrderReconciler}. It closes the order just like {@see close()}, only the state
     * says that the platform and not the app ended it.
     *
     * @throws PaymentException
     */
    public function expire(PaymentOrderEntity $order): void
    {
        // an order that was paid is never expired: the payment has to be booked, and the caller has
        // to know that it is not merely a repetition of an expiry
        if ($order->paidAt !== null) {
            throw PaymentException::orderAlreadyPaid($order->paymentNo);
        }

        if ($order->closedAt !== null) {
            throw PaymentException::orderAlreadyClosed($order->paymentNo);
        }

        // only an order the app never got pay parameters for may expire: once the channel was handed
        // the order the payer may still pay it, and expiring it would lose that payment
        if (!$this->isCreated($order)) {
            throw PaymentException::orderNotExpirable($order->paymentNo);
        }

        $this->stateHandler->expire($order->id);

        $this->paymentOrderRepository->update([[
            'id' => $order->id,
            'closedAt' => $this->clock->now(),
        ]], Context::createDefaultContext());
    }

    /**
     * Moves the order to `opened` right after the channel handed out its pay parameters.
     */
    public function markOpened(PaymentOrderEntity $order): void
    {
        if (!$this->isCreated($order)) {
            return;
        }

        $this->stateHandler->open($order->id);
    }

    public function isCreated(PaymentOrderEntity $order): bool
    {
        return $order->stateId === $this->initialStateIdLoader->get(PaymentOrderStates::STATE_MACHINE);
    }

    public function isPayable(PaymentOrderEntity $order): bool
    {
        return $order->paidAt === null && $order->closedAt === null;
    }

    /**
     * Marks a payment order as paid with the amount the channel confirmed.
     *
     * @param array<string, mixed>|null $channelData
     *
     * @throws PaymentException
     */
    public function markPaid(PaymentOrderEntity $order, int $paidAmount, ?string $outPaymentNo, ?array $channelData): void
    {
        $this->stateHandler->pay($order->id);

        $this->paymentOrderRepository->update([[
            'id' => $order->id,
            'paidAmount' => $paidAmount,
            'outPaymentNo' => $outPaymentNo,
            'channelData' => $channelData,
            'paidAt' => $this->clock->now(),
        ]], Context::createDefaultContext());
    }

    /**
     * @throws PaymentException
     */
    public function markFailed(PaymentOrderEntity $order): void
    {
        $this->stateHandler->fail($order->id);
    }

    /**
     * Books a settled refund on the payment order and keeps its state in sync: another partial refund
     * keeps the order partially refunded, a refund that covers the paid amount closes it as refunded.
     *
     * @throws PaymentException
     */
    public function applyRefund(PaymentOrderEntity $order, int $amount): PaymentOrderEntity
    {
        $refundedAmount = $order->refundedAmount + $amount;

        if ($refundedAmount >= $order->paidAmount) {
            $this->stateHandler->refund($order->id);
        } else {
            $this->stateHandler->refundPartially($order->id);
        }

        $this->paymentOrderRepository->update([[
            'id' => $order->id,
            'refundedAmount' => $refundedAmount,
        ]], Context::createDefaultContext());

        return $this->getById($order->id);
    }

    /**
     * @throws PaymentException
     */
    public function getById(string $id): PaymentOrderEntity
    {
        $criteria = new Criteria([$id]);
        $criteria->addAssociation('paymentApp');

        $order = $this->paymentOrderRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();

        if (!$order instanceof PaymentOrderEntity) {
            throw PaymentException::orderNotFound($id);
        }

        return $order;
    }

    /**
     * Expires the payment orders the payer never paid.
     *
     * Only orders that are still waiting for their pay parameters are expired: they were never handed
     * to a channel, so no payment can arrive for them anymore. An order that was handed over is left
     * alone, because the channel may report a late payment - and losing a payment that really happened
     * would be worse than an order that lingers.
     *
     * @return int the number of payment orders that were expired
     */
    public function expireDue(int $limit = 100): int
    {
        $criteria = new Criteria();
        // the created state is the initial state of the payment order state machine
        $criteria->addFilter(
            new EqualsFilter('stateId', $this->initialStateIdLoader->get(PaymentOrderStates::STATE_MACHINE))
        );
        // a date range filter compares strings, so the clock is formatted the way the storage does
        $criteria->addFilter(new RangeFilter('expireAt', [RangeFilter::LTE => $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT)]));
        $criteria->setLimit($limit);

        $orders = $this->paymentOrderRepository->search($criteria, Context::createDefaultContext())->getEntities();

        $expired = 0;
        foreach ($orders as $order) {
            if (!$order instanceof PaymentOrderEntity) {
                continue;
            }

            if (!$this->isPayable($order)) {
                continue;
            }

            $this->expire($order);

            ++$expired;
        }

        return $expired;
    }

    private function findByOutTradeNo(string $appId, string $outTradeNo): ?PaymentOrderEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('paymentAppId', $appId),
            new EqualsFilter('outTradeNo', $outTradeNo)
        );
        $criteria->setLimit(1);

        $order = $this->paymentOrderRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();

        return $order instanceof PaymentOrderEntity ? $order : null;
    }

    /**
     * The second unique key of a create: the app scoped idempotency key. An app that retries with the
     * same key but a fresh trade number is asking for the order it already created.
     */
    private function findByIdempotencyKey(string $appId, string $idempotencyKey): ?PaymentOrderEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('paymentAppId', $appId),
            new EqualsFilter('idempotencyKey', $idempotencyKey)
        );
        $criteria->setLimit(1);

        $order = $this->paymentOrderRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();

        return $order instanceof PaymentOrderEntity ? $order : null;
    }

    private function generatePaymentNo(\DateTimeImmutable $now): string
    {
        return 'PG' . $now->format('YmdHis') . strtoupper(bin2hex(random_bytes(4)));
    }
}
