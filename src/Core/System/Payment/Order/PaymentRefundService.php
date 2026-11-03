<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Order;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Payum\Core\Request\Refund as RefundRequest;
use Psr\Clock\ClockInterface;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\NumberRange\ValueGenerator\AbstractNumberRangeValueGenerator;
use Shopwell\Core\System\Payment\Api\Struct\PaymentRefundRequest;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\Exception\PaymentOrderException;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\Gateway\Request\NotificationResult;
use Shopwell\Core\System\Payment\Gateway\Request\RefundResult;
use Shopwell\Core\System\Payment\Notification\PaymentNotifyUrlBuilder;
use Shopwell\Core\System\Payment\Order\Aggregate\PaymentRefund\PaymentRefundCollection;
use Shopwell\Core\System\Payment\Order\Aggregate\PaymentRefund\PaymentRefundEntity;
use Shopwell\Core\System\Payment\Order\Event\PaymentRefundFinishedEvent;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\Capability\PaymentCapability;
use Shopwell\Core\System\Payment\Provider\Config\PaymentConfigResolver;
use Shopwell\Core\System\Payment\Provider\PaymentProvider;
use Shopwell\Core\System\Payment\Provider\ResolvedPaymentConfig;
use Shopwell\Core\System\Payment\Webhook\PaymentWebhookDispatcher;
use Shopwell\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Refunds of the generic payment gateway, partial refunds included.
 *
 * A refund is idempotent on (payment order, out refund number) - the number of the app - and it is
 * settled either synchronously or, when the channel confirms asynchronously, when its notification
 * arrives. Only a settled refund reduces the refundable amount of the payment order and notifies the
 * app, so the app never sees a refund that can still fail.
 *
 * @internal
 */
#[Package('framework')]
final class PaymentRefundService
{
    /**
     * @param EntityRepository<PaymentRefundCollection> $refundRepository
     */
    public function __construct(
        private readonly EntityRepository $refundRepository,
        private readonly PaymentConfigResolver $configResolver,
        private readonly PaymentOrderService $orderService,
        private readonly PaymentWebhookDispatcher $webhookService,
        private readonly PaymentNotifyUrlBuilder $notifyUrlBuilder,
        private readonly PaymentRefundStateHandler $stateHandler,
        private readonly InitialStateIdLoader $initialStateIdLoader,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ClockInterface $clock,
        private readonly AbstractNumberRangeValueGenerator $numberRanges,
        private readonly PaymentOrderRowLock $rowLock,
    ) {
    }

    /**
     * @throws PaymentException
     */
    public function refund(PaymentAppEntity $app, PaymentOrderEntity $order, PaymentRefundRequest $request): PaymentRefundEntity
    {
        // the replay of a refund the platform already knows is answered before anything is validated:
        // its amount is already booked on the order, so a second balance check would reject the very
        // refund that succeeded - and the channel must never be asked twice for the same refund
        $existing = $this->findByOutRefundNo($order->id, $request->outRefundNo);
        if ($existing instanceof PaymentRefundEntity) {
            return $existing;
        }

        $this->guardRequest($order, $request);

        // a refund runs on the interface alone, but the channel of the order is what was opened - and
        // its runtime parameters are the ones the order was created with. The credentials are resolved
        // before the refund row is written: a call the declaration already rules out must not leave a
        // refund behind that no channel ever saw
        $resolved = $this->configResolver->resolve(
            $app,
            $order->channelTechnicalName,
            $order->currency,
            $order->device,
            $order->channelParams ?? []
        );

        $id = Uuid::randomHex();
        $created = false;

        try {
            $refund = $this->rowLock->transaction(function () use (&$order, &$created, $id, $request, $resolved): PaymentRefundEntity {
                if ($this->rowLock->lockOrder($order->id) === null) {
                    throw PaymentOrderException::orderNotFound($order->id);
                }

                // The entity came from the caller before the lock. Re-read it and repeat the replay
                // lookup now that concurrent refund creation on this order is serialized.
                $order = $this->orderService->getById($order->id);
                $existing = $this->findByOutRefundNo($order->id, $request->outRefundNo);
                if ($existing instanceof PaymentRefundEntity) {
                    return $existing;
                }

                $reserved = $this->rowLock->reservedRefundAmount($order->id);
                $this->guard($order, $request, $resolved->provider, $reserved);

                $refund = $this->create($id, $order, $request);
                $created = true;

                return $refund;
            });
        } catch (UniqueConstraintViolationException $exception) {
            return $this->recoverCreateRace($order, $request, $exception);
        }

        if (!$created) {
            return $refund;
        }

        // The channel call is deliberately outside the creation transaction: no database row lock is
        // held while a remote interface is slow or unreachable.
        $model = $this->submit($app, $order, $refund, $resolved);
        $result = $model->result(RefundResult::class, $order->channelTechnicalName);

        if ($result->status === PaymentRefundStates::STATE_PROCESSING) {
            $this->rowLock->transaction(function () use ($refund, $model, $result): void {
                $this->recordResponse($refund, $model, null, $result->outRefundId);
                $this->stateHandler->process($refund->id);
            });

            return $this->getRefund($refund->id);
        }

        return $this->settle($refund, $order, $model, $result->outRefundId);
    }

    /**
     * Looks a refund up by the number of the platform, which is what a channel notification carries
     * back. There is no app scope here on purpose: the platform number is globally unique.
     */
    public function findByRefundNo(string $refundNo): ?PaymentRefundEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('refundNo', $refundNo));
        $criteria->setLimit(1);

        $refund = $this->refundRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();

        return $refund instanceof PaymentRefundEntity ? $refund : null;
    }

    /**
     * Applies a verified channel notification to a refund.
     *
     * A notification that does not confirm the refund only records the answer of the channel on the
     * refund: the channel may legitimately report a refund as still being processed, so a refund is
     * never moved to failed here. Definitive failures stay visible in the payment notifications.
     *
     * @throws PaymentException
     */
    public function applyNotification(
        PaymentRefundEntity $refund,
        PaymentOrderEntity $order,
        NotificationResult $result
    ): void {
        if (!$result->succeeded) {
            if ($refund->refundedAt !== null) {
                return;
            }

            if ($result->raw !== []) {
                $this->refundRepository->update([[
                    'id' => $refund->id,
                    'responsePayload' => $result->raw,
                ]], Context::createDefaultContext());
            }

            return;
        }

        // A successful replay still enters settle: its database booking is idempotent, and the
        // post-commit announcement is allowed to repair an event or webhook lost by an earlier worker.
        $this->settle($refund, $order, null, $result->outRefundId, $result->raw);
    }

    public function getRefund(string $id): PaymentRefundEntity
    {
        $refund = $this->refundRepository->search(new Criteria([$id]), Context::createDefaultContext())->getEntities()->first();

        if (!$refund instanceof PaymentRefundEntity) {
            throw PaymentOrderException::refundNotFound($id);
        }

        return $refund;
    }

    private function guardRequest(PaymentOrderEntity $order, PaymentRefundRequest $request, int $reserved = 0): int
    {
        if ($order->paidAt === null) {
            throw PaymentOrderException::refundNotAllowed($order->paymentNo);
        }

        if ($request->amount <= 0) {
            throw PaymentOrderException::invalidAmount($request->amount);
        }

        $available = $order->paidAmount - $order->refundedAmount - $reserved;
        if ($request->amount > $available) {
            throw PaymentOrderException::refundAmountExceedsOrder($order->paymentNo, $request->amount, $available);
        }

        return $available;
    }

    private function guard(
        PaymentOrderEntity $order,
        PaymentRefundRequest $request,
        PaymentProvider $provider,
        int $reserved
    ): void {
        $available = $this->guardRequest($order, $request, $reserved);

        // An interface that never declared a refund cannot give money back. Payum would answer the
        // refund request with "no action supports this request" inside the gateway, which the catch
        // below can only report as a channel fault (502) - while the declaration answered the very
        // same question before a request was signed. The guard reads the resolved provider, so a
        // plugin that adds a refund action makes refunding reachable for that interface.
        if (!$provider->supports(PaymentCapability::REFUND)) {
            throw PaymentException::providerLacksCapability($provider->technicalName, PaymentCapability::REFUND->value);
        }

        // A full refund - the entire amount that is still refundable - is the plain refund action every
        // interface that can refund at all can do, so `PARTIAL_REFUND` is deliberately not required for
        // it: an interface that declares `refund` alone must still be able to hand the whole payment
        // back, otherwise the only way out of a wrong payment would be closed to it. Only "less than
        // what is left" is the distinct operation some interfaces refuse, and that one has to be
        // declared. `available` is the remaining amount and not the order amount on purpose: a second
        // refund of what is left is a full refund again, not a partial one.
        if ($request->amount < $available && !$provider->supports(PaymentCapability::PARTIAL_REFUND)) {
            throw PaymentException::providerLacksCapability($provider->technicalName, PaymentCapability::PARTIAL_REFUND->value);
        }
    }

    private function submit(
        PaymentAppEntity $app,
        PaymentOrderEntity $order,
        PaymentRefundEntity $refund,
        ResolvedPaymentConfig $resolved
    ): PaymentGatewayModel {
        $model = PaymentGatewayModel::forRefund($order, $refund);
        $gateway = $resolved->provider->gatewayFactory->createForPayment(
            $resolved,
            $this->notifyUrlBuilder->build($app, $resolved->provider->technicalName, $order->paymentNo),
            (string) $order->returnUrl
        );

        try {
            $gateway->execute(new RefundRequest($model));
        } catch (\Throwable $exception) {
            $this->rowLock->transaction(function () use ($refund, $model, $exception): void {
                $this->recordResponse($refund, $model, $exception);
                $this->stateHandler->fail($refund->id);
            });

            throw PaymentException::gatewayError($order->channelTechnicalName, $exception->getMessage(), $exception);
        }

        return $model;
    }

    private function recoverCreateRace(
        PaymentOrderEntity $order,
        PaymentRefundRequest $request,
        UniqueConstraintViolationException $exception
    ): PaymentRefundEntity {
        // The unique key over (order, out refund number) is the second line of defence. Its winner is
        // the answer, and returning here means the channel is never asked twice for the same refund.
        $existing = $this->findByOutRefundNo($order->id, $request->outRefundNo);
        if ($existing instanceof PaymentRefundEntity) {
            return $existing;
        }

        throw $exception;
    }

    private function create(string $id, PaymentOrderEntity $order, PaymentRefundRequest $request): PaymentRefundEntity
    {
        $this->refundRepository->create([[
            'id' => $id,
            'refundNo' => $this->numberRanges->getValue(PaymentRefundEntity::ENTITY_NAME, Context::createDefaultContext(), null),
            'paymentOrderId' => $order->id,
            'outRefundNo' => $request->outRefundNo,
            'amount' => $request->amount,
            'currency' => $order->currency,
            'reason' => $request->reason,
            'stateId' => $this->initialStateIdLoader->get(PaymentRefundStates::STATE_MACHINE),
        ]], Context::createDefaultContext());

        return $this->getRefund($id);
    }

    /**
     * Books the settled refund on the payment order, notifies the app and moves the refund to finished.
     *
     * @param array<string, mixed>|null $response
     *
     * @throws PaymentException
     */
    private function settle(
        PaymentRefundEntity $refund,
        PaymentOrderEntity $order,
        ?PaymentGatewayModel $model,
        ?string $outRefundId,
        ?array $response = null
    ): PaymentRefundEntity {
        $responsePayload = $response ?? ($model === null ? null : $model->response());

        $refund = $this->rowLock->transaction(function () use ($refund, $order, $outRefundId, $responsePayload): PaymentRefundEntity {
            // Settlement always takes the refund lock first. A second worker waits here and then sees
            // the committed timestamp instead of adding the same amount to the order a second time.
            $settled = $this->rowLock->lockRefund($refund->id);
            if ($settled === null) {
                throw PaymentOrderException::refundNotFound($refund->id);
            }

            $refund = $this->getRefund($refund->id);
            if ($settled) {
                // Keep the same lock order on a replay. No amount is written, but the fresh order is
                // needed to repair the announcement with the committed refunded amount.
                if ($this->rowLock->lockOrder($order->id) === null) {
                    throw PaymentOrderException::orderNotFound($order->id);
                }

                return $refund;
            }

            // applyRefund takes the order lock and writes its state and amount in this same
            // transaction. The refund state, timestamp and response join that atomic booking.
            $this->orderService->applyRefund($order, $refund->amount);
            $this->stateHandler->finish($refund->id);

            $this->refundRepository->update([[
                'id' => $refund->id,
                'outRefundId' => $outRefundId,
                'refundedAt' => $this->clock->now(),
                'responsePayload' => $responsePayload,
            ]], Context::createDefaultContext());

            return $this->getRefund($refund->id);
        });

        // The transaction has committed here. Repeating a notification repeats these idempotent
        // effects on purpose, so a worker that died between commit and announcement can be repaired.
        $order = $this->orderService->getById($order->id);
        $this->eventDispatcher->dispatch(new PaymentRefundFinishedEvent($refund, $order, Context::createDefaultContext()));

        // a refund is announced per refund and not per order: an order can be refunded more than once
        $this->webhookService->enqueue($order, PaymentWebhookDispatcher::EVENT_REFUND_FINISHED, [
            'refund_no' => $refund->refundNo,
            'out_refund_no' => $refund->outRefundNo,
            'out_refund_id' => $refund->outRefundId,
            'amount' => $refund->amount,
            'currency' => $refund->currency,
            'refunded_amount' => $order->refundedAmount,
            'state' => PaymentRefundStates::STATE_FINISHED,
        ], $refund->id);

        return $refund;
    }

    private function recordResponse(
        PaymentRefundEntity $refund,
        PaymentGatewayModel $model,
        ?\Throwable $exception = null,
        ?string $outRefundId = null
    ): void {
        $response = $model->response();

        if ($exception !== null) {
            $response['exception'] = $exception->getMessage();
        }

        $this->refundRepository->update([[
            'id' => $refund->id,
            'outRefundId' => $outRefundId,
            'requestPayload' => $model->request(),
            'responsePayload' => $response,
        ]], Context::createDefaultContext());
    }

    private function findByOutRefundNo(string $paymentOrderId, string $outRefundNo): ?PaymentRefundEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('paymentOrderId', $paymentOrderId),
            new EqualsFilter('outRefundNo', $outRefundNo)
        );
        $criteria->setLimit(1);

        $refund = $this->refundRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();

        return $refund instanceof PaymentRefundEntity ? $refund : null;
    }
}
