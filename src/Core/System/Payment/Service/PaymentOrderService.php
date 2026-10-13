<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Service;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Psr\Clock\ClockInterface;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Payment\DataAbstractionLayer\PaymentAppEntity;
use Shopwell\Core\System\Payment\DataAbstractionLayer\PaymentOrderCollection;
use Shopwell\Core\System\Payment\DataAbstractionLayer\PaymentOrderEntity;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\PaymentOrderStates;
use Shopwell\Core\System\Payment\Struct\PaymentOrderRequest;
use Shopwell\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Shopwell\Core\System\StateMachine\StateMachineRegistry;
use Shopwell\Core\System\StateMachine\Transition;

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
        private readonly StateMachineRegistry $stateMachineRegistry,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * @throws PaymentException
     */
    public function create(PaymentAppEntity $app, PaymentOrderRequest $request): PaymentOrderEntity
    {
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

        try {
            $this->paymentOrderRepository->create([[
                'id' => $id,
                'paymentNo' => $this->generatePaymentNo($now),
                'outTradeNo' => $request->outTradeNo,
                'paymentAppId' => $app->id,
                'providerTechnicalName' => $resolved->provider->technicalName,
                'channelTechnicalName' => $request->channel,
                'channelParams' => $request->channelParams === [] ? null : $request->channelParams,
                'amount' => $request->amount,
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
            ]], Context::createDefaultContext());
        } catch (UniqueConstraintViolationException) {
            // a parallel request created the very same order, hand it out instead of failing
            $race = $this->findByOutTradeNo($app->id, $request->outTradeNo);
            if ($race === null) {
                throw PaymentException::orderNotFound($request->outTradeNo);
            }

            return $race;
        }

        return $this->getById($id);
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
     * @throws PaymentException
     */
    public function close(PaymentOrderEntity $order): void
    {
        if ($order->closedAt !== null || $order->paidAt !== null) {
            throw PaymentException::orderAlreadyClosed($order->paymentNo);
        }

        $this->transition($order, PaymentOrderStates::ACTION_CLOSE);

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

        $this->transition($order, PaymentOrderStates::ACTION_PROCESS);
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
        $this->transition($order, PaymentOrderStates::ACTION_PAY);

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
        $this->transition($order, PaymentOrderStates::ACTION_FAIL);
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

        $this->transition(
            $order,
            $refundedAmount >= $order->paidAmount ? PaymentOrderStates::ACTION_REFUND : PaymentOrderStates::ACTION_REFUND_PARTIALLY
        );

        $this->paymentOrderRepository->update([[
            'id' => $order->id,
            'refundedAmount' => $refundedAmount,
        ]], Context::createDefaultContext());

        return $this->getById($order->id);
    }

    /**
     * Applies a state machine transition to a payment order.
     */
    public function transition(PaymentOrderEntity $order, string $action): void
    {
        $this->stateMachineRegistry->transition(
            new Transition(PaymentOrderEntity::ENTITY_NAME, $order->id, $action, 'stateId'),
            Context::createDefaultContext()
        );
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

            $this->transition($order, PaymentOrderStates::ACTION_EXPIRE);
            $this->paymentOrderRepository->update([[
                'id' => $order->id,
                'closedAt' => $this->clock->now(),
            ]], Context::createDefaultContext());

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

    private function generatePaymentNo(\DateTimeImmutable $now): string
    {
        return 'PG' . $now->format('YmdHis') . strtoupper(bin2hex(random_bytes(4)));
    }
}
