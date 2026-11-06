<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Order\Refund;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Payum\Core\GatewayInterface;
use Payum\Core\Request\Refund as RefundRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\NumberRange\ValueGenerator\AbstractNumberRangeValueGenerator;
use Shopwell\Core\System\Payment\App\PaymentAppCollection;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\App\PaymentCredentialCipher;
use Shopwell\Core\System\Payment\App\PaymentSignatureService;
use Shopwell\Core\System\Payment\Exception\PaymentGatewayException;
use Shopwell\Core\System\Payment\Exception\PaymentOrderException;
use Shopwell\Core\System\Payment\Exception\PaymentProviderException;
use Shopwell\Core\System\Payment\Exception\PaymentRefundRejectedException;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayFactory;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\Gateway\Request\NotificationResult;
use Shopwell\Core\System\Payment\Gateway\Request\RefundQuery;
use Shopwell\Core\System\Payment\Gateway\Request\RefundResult;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationExtractorInterface;
use Shopwell\Core\System\Payment\Notification\PaymentNotifyUrlBuilder;
use Shopwell\Core\System\Payment\Order\Aggregate\PaymentAttempt\PaymentAttemptCollection;
use Shopwell\Core\System\Payment\Order\Aggregate\PaymentAttempt\PaymentAttemptEntity;
use Shopwell\Core\System\Payment\Order\Aggregate\PaymentRefund\PaymentRefundCollection;
use Shopwell\Core\System\Payment\Order\Aggregate\PaymentRefund\PaymentRefundEntity;
use Shopwell\Core\System\Payment\Order\Event\PaymentEventDispatcher;
use Shopwell\Core\System\Payment\Order\Event\PaymentRefundFinishedEvent;
use Shopwell\Core\System\Payment\Order\PaymentOrderCollection;
use Shopwell\Core\System\Payment\Order\PaymentOrderEntity;
use Shopwell\Core\System\Payment\Order\PaymentOrderRowLock;
use Shopwell\Core\System\Payment\Order\PaymentOrderService;
use Shopwell\Core\System\Payment\Order\PaymentOrderStateHandler;
use Shopwell\Core\System\Payment\Order\PaymentOrderStates;
use Shopwell\Core\System\Payment\Order\PaymentRefundService;
use Shopwell\Core\System\Payment\Order\PaymentRefundStateHandler;
use Shopwell\Core\System\Payment\Order\PaymentRefundStates;
use Shopwell\Core\System\Payment\Order\Struct\PaymentRefundRequest;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Product\PaymentInteraction;
use Shopwell\Core\System\Payment\Product\PaymentProduct;
use Shopwell\Core\System\Payment\Provider\Capability\PaymentCapability;
use Shopwell\Core\System\Payment\Provider\Config\PaymentConfigResolver;
use Shopwell\Core\System\Payment\Provider\PaymentProvider;
use Shopwell\Core\System\Payment\Provider\ResolvedPaymentConfig;
use Shopwell\Core\System\Payment\Webhook\PaymentWebhookDeliveryCollection;
use Shopwell\Core\System\Payment\Webhook\PaymentWebhookDeliveryEntity;
use Shopwell\Core\System\Payment\Webhook\PaymentWebhookDispatcher;
use Shopwell\Core\System\Payment\Webhook\PaymentWebhookSigner;
use Shopwell\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Shopwell\Core\System\StateMachine\StateMachineRegistry;
use Shopwell\Core\System\StateMachine\Transition;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopwell\Tests\Unit\Core\System\Payment\NumberRangeFixture;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentRefundService::class)]
class PaymentRefundServiceTest extends TestCase
{
    private const NOW = '2024-01-01T00:00:00+00:00';

    private const CREATED_STATE = 'state-created';

    /**
     * What alipay, wechat and the two card interfaces declare: they refund, and they refund less than
     * the whole payment.
     */
    private const REFUND_CAPABILITIES = [
        PaymentCapability::CAPTURE,
        PaymentCapability::REFUND,
        PaymentCapability::PARTIAL_REFUND,
    ];

    /**
     * An interface that can only hand the whole payment back.
     */
    private const FULL_REFUND_ONLY_CAPABILITIES = [PaymentCapability::CAPTURE, PaymentCapability::REFUND];

    public function testRefundThrowsWhenTheOrderWasNeverPaid(): void
    {
        $refundRepository = static::createMock(EntityRepository::class);
        $refundRepository->expects($this->never())->method('create');

        $service = $this->refundService($refundRepository, $this->gateway(null));

        $this->expectExceptionObject(PaymentOrderException::refundNotAllowed('PG0001'));

        $service->refund($this->app(), $this->order(), $this->refundRequest(1000));
    }

    public function testRefundThrowsForANonPositiveAmount(): void
    {
        $refundRepository = static::createMock(EntityRepository::class);
        $refundRepository->expects($this->never())->method('create');

        $service = $this->refundService($refundRepository, $this->gateway(null));

        $this->expectExceptionObject(PaymentOrderException::invalidAmount(0));

        $service->refund($this->app(), $this->paidOrder(), $this->refundRequest(0));
    }

    public function testRefundThrowsWhenTheAmountExceedsTheRefundableAmount(): void
    {
        $refundRepository = static::createMock(EntityRepository::class);
        $refundRepository->expects($this->never())->method('create');

        $order = $this->paidOrder();

        $service = $this->refundService($refundRepository, $this->gateway(null));

        $this->expectExceptionObject(PaymentOrderException::refundAmountExceedsOrder('PG0001', 1001, 1000));

        $service->refund($this->app(), $order, $this->refundRequest(1001));
    }

    public function testRefundCountsUnsettledRefundsAsReserved(): void
    {
        $refundRepository = static::createMock(EntityRepository::class);
        $refundRepository->method('search')->willReturn($this->refundResult());
        $refundRepository->expects($this->never())->method('create');

        $gateway = static::createMock(GatewayInterface::class);
        $gateway->expects($this->never())->method('execute');

        $rowLock = $this->rowLock(reserved: 600);
        $service = $this->refundService($refundRepository, $gateway, rowLock: $rowLock);

        $this->expectExceptionObject(PaymentOrderException::refundAmountExceedsOrder('PG0001', 500, 400));

        $service->refund($this->app(), $this->paidOrder(), $this->refundRequest(500));
    }

    #[DataProvider('mismatchedRefundReplays')]
    public function testARefundNumberCannotBeReusedForDifferentBusinessParameters(int $amount, ?string $reason, bool $concurrentWinner): void
    {
        $existing = $this->refundEntity();
        $searches = $concurrentWinner ? [new PaymentRefundCollection()] : [];
        $searches[] = new PaymentRefundCollection([$existing]);
        $repository = new StaticEntityRepository($searches);
        $gateway = static::createMock(GatewayInterface::class);
        $gateway->expects($this->never())->method('execute');
        $service = $this->refundService($repository, $gateway);

        $this->expectExceptionObject(PaymentOrderException::refundIdempotencyMismatch('app-refund-1'));

        $service->refund($this->app(), $this->paidOrder(), new PaymentRefundRequest('app-refund-1', $amount, $reason));
    }

    /**
     * @return \Generator<string, array{int, ?string, bool}>
     */
    public static function mismatchedRefundReplays(): \Generator
    {
        yield 'a changed amount conflicts with the initial replay' => [500, null, false];
        yield 'a changed reason conflicts with the initial replay' => [1000, 'different reason', false];
        yield 'a changed amount conflicts with the concurrent winner' => [500, null, true];
    }

    public function testRefundReturnsTheExistingRefundForAKnownOutRefundNo(): void
    {
        $existing = $this->refundEntity();

        $refundRepository = static::createMock(EntityRepository::class);
        $refundRepository->method('search')->willReturn($this->refundResult($existing));
        $refundRepository->expects($this->never())->method('create');

        $gateway = static::createMock(GatewayInterface::class);
        $gateway->expects($this->never())->method('execute');

        $service = $this->refundService($refundRepository, $gateway);

        static::assertSame($existing, $service->refund($this->app(), $this->paidOrder(), $this->refundRequest(1000)));
    }

    /**
     * The replay of a refund that already settled: its amount is booked, so the refundable amount is
     * exhausted - the idempotency lookup has to answer before the balance check, otherwise the very
     * refund that succeeded would be rejected as an over-refund, and the channel would be asked twice.
     */
    public function testRefundAnswersTheReplayOfASettledFullRefundWithoutTouchingTheChannel(): void
    {
        $existing = $this->refundEntity(['refundedAt' => new \DateTimeImmutable(self::NOW)]);

        $refundRepository = static::createMock(EntityRepository::class);
        $refundRepository->method('search')->willReturn($this->refundResult($existing));
        $refundRepository->expects($this->never())->method('create');
        $refundRepository->expects($this->never())->method('update');

        $gateway = static::createMock(GatewayInterface::class);
        $gateway->expects($this->never())->method('execute');

        $order = $this->order([
            'paidAt' => new \DateTimeImmutable(self::NOW),
            'paidAmount' => 1000,
            'refundedAmount' => 1000,
            'stateId' => 'state-refunded',
        ]);

        $service = $this->refundService($refundRepository, $gateway);

        static::assertSame($existing, $service->refund($this->app(), $order, $this->refundRequest(1000)));
    }

    /**
     * Two workers can pass the replay lookup at the same time. The unique key over (order, out refund
     * number) is the second line of defence, and hitting it is not an error: the row the other worker
     * booked is the answer, and the channel is never asked twice for the same refund.
     */
    public function testRefundRecoversTheRefundThatWasBookedBetweenLookupAndInsert(): void
    {
        $existing = $this->refundEntity();
        $lookups = 0;

        $refundRepository = static::createMock(EntityRepository::class);
        $refundRepository->method('search')->willReturnCallback(
            function (Criteria $criteria) use (&$lookups, $existing): EntitySearchResult {
                if ($criteria->getIds() !== []) {
                    return $this->refundResult();
                }

                // both replay lookups find nothing, the lookup after the unique key conflict finds the row
                ++$lookups;

                return $lookups < 3 ? $this->refundResult() : $this->refundResult($existing);
            }
        );
        $refundRepository->expects($this->once())
            ->method('create')
            ->willThrowException(static::createStub(UniqueConstraintViolationException::class));

        $gateway = static::createMock(GatewayInterface::class);
        $gateway->expects($this->never())->method('execute');

        $service = $this->refundService($refundRepository, $gateway);

        static::assertSame($existing, $service->refund($this->app(), $this->paidOrder(), $this->refundRequest(1000)));
    }

    public function testRefundSettlesSynchronouslyAndNotifiesTheApp(): void
    {
        $refund = $this->refundEntity();

        $refundRepository = static::createMock(EntityRepository::class);
        $refundRepository->method('search')->willReturnCallback(
            fn (Criteria $criteria): EntitySearchResult => $criteria->getIds() === []
                ? $this->refundResult()
                : $this->refundResult($refund)
        );
        $refundRepository->expects($this->once())->method('create');
        $refundRepository->expects($this->once())
            ->method('update')
            ->with(static::callback(function (array $data): bool {
                static::assertSame('refund-id', $data[0]['id']);
                static::assertSame('channel-refund-1', $data[0]['outRefundId']);

                $refundedAt = $data[0]['refundedAt'];
                static::assertInstanceOf(\DateTimeInterface::class, $refundedAt);
                static::assertSame(self::NOW, $refundedAt->format(\DateTimeInterface::ATOM));

                return true;
            }));

        $refundStateMachine = static::createMock(StateMachineRegistry::class);
        $refundStateMachine->expects($this->once())
            ->method('transition')
            ->with(static::callback(static fn (Transition $t): bool => $t->getTransitionName() === PaymentRefundStates::ACTION_FINISH));

        $orderStateMachine = static::createMock(StateMachineRegistry::class);
        $orderStateMachine->expects($this->once())
            ->method('transition')
            ->with(static::callback(static fn (Transition $t): bool => $t->getTransitionName() === PaymentOrderStates::ACTION_REFUND));

        $eventDispatcher = static::createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(static::isInstanceOf(PaymentRefundFinishedEvent::class));

        $service = $this->refundService(
            $refundRepository,
            $this->gateway(PaymentRefundStates::STATE_FINISHED, 'channel-refund-1'),
            $this->orderService($this->orderRepository($this->paidOrder()), $orderStateMachine),
            $refundStateMachine,
            $eventDispatcher
        );

        static::assertSame($refund, $service->refund($this->app(), $this->paidOrder(), $this->refundRequest(1000)));
    }

    public function testRefundStaysProcessingWhenTheChannelWorksAsynchronously(): void
    {
        $refund = $this->refundEntity();

        $refundRepository = static::createMock(EntityRepository::class);
        $refundRepository->method('search')->willReturnCallback(
            fn (Criteria $criteria): EntitySearchResult => $criteria->getIds() === []
                ? $this->refundResult()
                : $this->refundResult($refund)
        );
        $refundRepository->expects($this->once())->method('create');
        $refundRepository->expects($this->once())
            ->method('update')
            ->with(static::callback(function (array $data): bool {
                static::assertSame('channel-refund-1', $data[0]['outRefundId']);
                static::assertArrayNotHasKey('refundedAt', $data[0]);

                return true;
            }));

        $refundStateMachine = static::createMock(StateMachineRegistry::class);
        $refundStateMachine->expects($this->once())
            ->method('transition')
            ->with(static::callback(static fn (Transition $t): bool => $t->getTransitionName() === PaymentRefundStates::ACTION_PROCESS));

        $eventDispatcher = static::createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->never())->method('dispatch');

        $service = $this->refundService(
            $refundRepository,
            $this->gateway(PaymentRefundStates::STATE_PROCESSING, 'channel-refund-1'),
            $this->orderService($this->orderRepository($this->paidOrder())),
            $refundStateMachine,
            $eventDispatcher
        );

        static::assertSame($refund, $service->refund($this->app(), $this->paidOrder(), $this->refundRequest(1000)));
    }

    public function testRefundSubmitsToTheChannelOutsideTheDatabaseTransaction(): void
    {
        $refund = $this->refundEntity();
        $transactionState = new \ArrayObject(['active' => false]);

        $rowLock = static::createStub(PaymentOrderRowLock::class);
        $rowLock->method('transaction')->willReturnCallback(
            static function (\Closure $write) use ($transactionState): mixed {
                $transactionState->offsetSet('active', true);

                try {
                    return $write();
                } finally {
                    $transactionState->offsetSet('active', false);
                }
            }
        );
        $rowLock->method('lockOrder')->willReturn(['paidAmount' => 1000, 'refundedAmount' => 0]);
        $rowLock->method('lockRefund')->willReturn(false);
        $rowLock->method('reservedRefundAmount')->willReturn(0);

        $gateway = static::createStub(GatewayInterface::class);
        $gateway->method('execute')->willReturnCallback(
            static function (object $request) use ($transactionState): void {
                static::assertFalse($transactionState->offsetGet('active'));
                static::assertInstanceOf(RefundRequest::class, $request);

                $model = $request->getModel();
                static::assertInstanceOf(PaymentGatewayModel::class, $model);
                $model->offsetSet(PaymentGatewayModel::RESULT, new RefundResult(PaymentRefundStates::STATE_PROCESSING));
            }
        );

        $refundRepository = static::createStub(EntityRepository::class);
        $refundRepository->method('search')->willReturnCallback(
            fn (Criteria $criteria): EntitySearchResult => $criteria->getIds() === []
                ? $this->refundResult()
                : $this->refundResult($refund)
        );

        $result = $this->refundService($refundRepository, $gateway, rowLock: $rowLock)
            ->refund($this->app(), $this->paidOrder(), $this->refundRequest(1000));

        static::assertSame($refund, $result);
        static::assertFalse($transactionState->offsetGet('active'));
    }

    public function testRefundKeepsTheReservationWhenTheChannelOutcomeIsUnknown(): void
    {
        $refund = $this->refundEntity();

        $refundRepository = static::createMock(EntityRepository::class);
        $refundRepository->method('search')->willReturnCallback(
            fn (Criteria $criteria): EntitySearchResult => $criteria->getIds() === []
                ? $this->refundResult()
                : $this->refundResult($refund)
        );
        $refundRepository->expects($this->once())->method('create');
        $refundRepository->expects($this->once())
            ->method('update')
            ->with(static::callback(function (array $data): bool {
                static::assertSame('boom', $data[0]['responsePayload']['exception']);

                return true;
            }));

        $refundStateMachine = static::createMock(StateMachineRegistry::class);
        $refundStateMachine->expects($this->never())->method('transition');

        $service = $this->refundService(
            $refundRepository,
            $this->failingGateway(),
            $this->orderService($this->orderRepository($this->paidOrder())),
            $refundStateMachine
        );

        $this->expectExceptionObject(PaymentGatewayException::gatewayError('alipay_h5', 'boom'));

        $service->refund($this->app(), $this->paidOrder(), $this->refundRequest(1000));
    }

    /**
     * An interface that never declared a refund cannot give money back: the declaration answers that
     * before a request is signed, so the caller gets a 400 naming the capability instead of a 502 that
     * reads like a broken channel - and no refund row is written for a call that never happened.
     */
    public function testProductWithoutRefundIsRefusedBeforeBookingOrCallingTheProvider(): void
    {
        $refunds = static::createMock(EntityRepository::class);
        $refunds->method('search')->willReturn($this->refundResult());
        $refunds->expects($this->never())->method('create');
        $gateway = static::createMock(GatewayInterface::class);
        $gateway->expects($this->never())->method('execute');
        $this->expectExceptionObject(PaymentProviderException::providerLacksCapability('alipay', PaymentCapability::REFUND->value));

        $this->refundService($refunds, $gateway, productCapabilities: [PaymentCapability::CAPTURE])->refund($this->app(), $this->paidOrder(), $this->refundRequest(1000));
    }

    public function testProductWithoutPartialRefundIsRefusedWhenTheProviderSupportsIt(): void
    {
        $refunds = static::createMock(EntityRepository::class);
        $refunds->method('search')->willReturn($this->refundResult());
        $refunds->expects($this->never())->method('create');
        $gateway = static::createMock(GatewayInterface::class);
        $gateway->expects($this->never())->method('execute');
        $this->expectExceptionObject(PaymentProviderException::providerLacksCapability('alipay', PaymentCapability::PARTIAL_REFUND->value));

        $this->refundService($refunds, $gateway, productCapabilities: self::FULL_REFUND_ONLY_CAPABILITIES)->refund($this->app(), $this->paidOrder(), $this->refundRequest(500));
    }

    public function testAnInterfaceWithoutRefundIsRefusedBeforeTheAttemptIsBooked(): void
    {
        $refundRepository = static::createMock(EntityRepository::class);
        $refundRepository->method('search')->willReturn($this->refundResult());
        $refundRepository->expects($this->never())->method('create');
        $refundRepository->expects($this->never())->method('update');

        $gateway = static::createMock(GatewayInterface::class);
        $gateway->expects($this->never())->method('execute');

        $service = $this->refundService($refundRepository, $gateway, capabilities: [PaymentCapability::CAPTURE]);

        try {
            $service->refund($this->app(), $this->paidOrder(), $this->refundRequest(1000));
            static::fail('the missing refund capability has to be reported');
        } catch (PaymentException $exception) {
            static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
            static::assertSame(PaymentException::PROVIDER_LACKS_CAPABILITY, $exception->getErrorCode());
            static::assertStringContainsString(PaymentCapability::REFUND->value, $exception->getMessage());
            static::assertStringNotContainsString('returned an error', $exception->getMessage());
        }
    }

    /**
     * A partial refund is the distinct operation: an interface that declares only `refund` hands back
     * the whole payment and nothing less, so less than the remaining amount is refused - with the
     * capability that is missing, again before anything is booked.
     */
    public function testAPartialRefundIsRefusedWithoutThePartialRefundCapability(): void
    {
        $refundRepository = static::createMock(EntityRepository::class);
        $refundRepository->method('search')->willReturn($this->refundResult());
        $refundRepository->expects($this->never())->method('create');
        $refundRepository->expects($this->never())->method('update');

        $gateway = static::createMock(GatewayInterface::class);
        $gateway->expects($this->never())->method('execute');

        $service = $this->refundService($refundRepository, $gateway, capabilities: self::FULL_REFUND_ONLY_CAPABILITIES);

        $this->expectExceptionObject(PaymentProviderException::providerLacksCapability('alipay', PaymentCapability::PARTIAL_REFUND->value));

        // 500 of 1000 still refundable is a partial refund
        $service->refund($this->app(), $this->paidOrder(), $this->refundRequest(500));
    }

    /**
     * The full amount that is left is not a partial refund, so an interface that declares `refund`
     * alone may still hand the whole payment back - refusing it there would close the only way out of
     * a wrong payment.
     */
    public function testAFullRefundNeedsNoPartialRefundCapability(): void
    {
        $refund = $this->refundEntity();

        $refundRepository = static::createMock(EntityRepository::class);
        $refundRepository->method('search')->willReturnCallback(
            fn (Criteria $criteria): EntitySearchResult => $criteria->getIds() === []
                ? $this->refundResult()
                : $this->refundResult($refund)
        );
        $refundRepository->expects($this->once())->method('create');
        $refundRepository->expects($this->once())->method('update');

        $refundStateMachine = static::createMock(StateMachineRegistry::class);
        $refundStateMachine->expects($this->once())
            ->method('transition')
            ->with(static::callback(static fn (Transition $t): bool => $t->getTransitionName() === PaymentRefundStates::ACTION_FINISH));

        $service = $this->refundService(
            $refundRepository,
            $this->gateway(PaymentRefundStates::STATE_FINISHED, 'channel-refund-1'),
            $this->orderService($this->orderRepository($this->paidOrder())),
            $refundStateMachine,
            capabilities: self::FULL_REFUND_ONLY_CAPABILITIES
        );

        static::assertSame($refund, $service->refund($this->app(), $this->paidOrder(), $this->refundRequest(1000)));
    }

    /**
     * The remaining amount is what "full" means: a second refund of what the first one left is a full
     * refund again, not a partial one - otherwise an interface without `partial_refund` could refund
     * exactly once and never finish the job.
     */
    public function testRefundingTheRemainderOfAnAlreadyRefundedOrderIsAFullRefund(): void
    {
        $refund = $this->refundEntity(['amount' => 400]);

        $refundRepository = static::createMock(EntityRepository::class);
        $refundRepository->method('search')->willReturnCallback(
            fn (Criteria $criteria): EntitySearchResult => $criteria->getIds() === []
                ? $this->refundResult()
                : $this->refundResult($refund)
        );
        $refundRepository->expects($this->once())->method('create');
        $refundRepository->expects($this->once())->method('update');

        $refundStateMachine = static::createStub(StateMachineRegistry::class);

        $order = $this->order([
            'paidAt' => new \DateTimeImmutable(self::NOW),
            'paidAmount' => 1000,
            'refundedAmount' => 600,
            'stateId' => 'state-partially-refunded',
        ]);

        $rowLock = $this->rowLock(['paidAmount' => 1000, 'refundedAmount' => 600]);
        $service = $this->refundService(
            $refundRepository,
            $this->gateway(PaymentRefundStates::STATE_FINISHED, 'channel-refund-1'),
            $this->orderService($this->orderRepository($order), null, $rowLock),
            $refundStateMachine,
            capabilities: self::FULL_REFUND_ONLY_CAPABILITIES,
            rowLock: $rowLock
        );

        // 400 of the 400 that are left
        static::assertSame($refund, $service->refund($this->app(), $order, $this->refundRequest(400)));
    }

    public function testFindByRefundNoReturnsTheRefund(): void
    {
        $refund = $this->refundEntity();

        $refundRepository = static::createStub(EntityRepository::class);
        $refundRepository->method('search')->willReturn($this->refundResult($refund));

        static::assertSame($refund, $this->refundService($refundRepository, $this->gateway(null))->findByRefundNo('PR0001'));
    }

    public function testFindByRefundNoReturnsNullWhenUnknown(): void
    {
        $refundRepository = static::createStub(EntityRepository::class);
        $refundRepository->method('search')->willReturn($this->refundResult());

        static::assertNull($this->refundService($refundRepository, $this->gateway(null))->findByRefundNo('PR0001'));
    }

    public function testGetRefundThrowsWhenItDoesNotExist(): void
    {
        $refundRepository = static::createStub(EntityRepository::class);
        $refundRepository->method('search')->willReturn($this->refundResult());

        $this->expectExceptionObject(PaymentOrderException::refundNotFound('missing-id'));

        $this->refundService($refundRepository, $this->gateway(null))->getRefund('missing-id');
    }

    public function testApplyNotificationReplaysTheAnnouncementWithoutBookingASettledRefundAgain(): void
    {
        $refund = $this->refundEntity(['refundedAt' => new \DateTimeImmutable(self::NOW)]);

        $refundRepository = static::createMock(EntityRepository::class);
        $refundRepository->method('search')->willReturn($this->refundResult($refund));
        $refundRepository->expects($this->never())->method('update');

        $orderRepository = static::createMock(EntityRepository::class);
        $orderRepository->method('search')->willReturn(new EntitySearchResult(
            PaymentOrderEntity::ENTITY_NAME,
            1,
            new PaymentOrderCollection([$this->paidOrder()]),
            null,
            new Criteria(),
            Context::createDefaultContext()
        ));
        $orderRepository->expects($this->never())->method('update');

        $refundStateMachine = static::createMock(StateMachineRegistry::class);
        $refundStateMachine->expects($this->never())->method('transition');
        $orderStateMachine = static::createMock(StateMachineRegistry::class);
        $orderStateMachine->expects($this->never())->method('transition');

        $eventDispatcher = static::createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())->method('dispatch');

        $rowLock = $this->rowLock(settled: true);
        $this->refundService(
            $refundRepository,
            $this->gateway(null),
            $this->orderService($orderRepository, $orderStateMachine, $rowLock),
            $refundStateMachine,
            $eventDispatcher,
            rowLock: $rowLock
        )->applyNotification($refund, $this->paidOrder(), new NotificationResult(true, true, amount: 1000, raw: ['status' => 'done']));
    }

    public function testApplyNotificationRecordsTheAnswerWithoutSettlingWhenTheChannelDidNotSucceed(): void
    {
        $refund = $this->refundEntity();

        $refundRepository = static::createMock(EntityRepository::class);
        $refundRepository->expects($this->once())
            ->method('update')
            ->with(static::callback(function (array $data): bool {
                static::assertSame(['status' => 'processing'], $data[0]['responsePayload']);
                static::assertArrayNotHasKey('refundedAt', $data[0]);

                return true;
            }));

        $refundStateMachine = static::createMock(StateMachineRegistry::class);
        $refundStateMachine->expects($this->never())->method('transition');

        $this->refundService($refundRepository, $this->gateway(null), null, $refundStateMachine)
            ->applyNotification($refund, $this->paidOrder(), new NotificationResult(true, false, amount: 1000, raw: ['status' => 'processing']));
    }

    public function testApplyNotificationSettlesTheRefundWhenTheChannelConfirmed(): void
    {
        $refund = $this->refundEntity();

        $refundRepository = static::createMock(EntityRepository::class);
        $refundRepository->method('search')->willReturn($this->refundResult($refund));
        $refundRepository->expects($this->once())->method('update');

        $refundStateMachine = static::createMock(StateMachineRegistry::class);
        $refundStateMachine->expects($this->once())
            ->method('transition')
            ->with(static::callback(static fn (Transition $t): bool => $t->getTransitionName() === PaymentRefundStates::ACTION_FINISH));

        $orderStateMachine = static::createMock(StateMachineRegistry::class);
        $orderStateMachine->expects($this->once())
            ->method('transition')
            ->with(static::callback(static fn (Transition $t): bool => $t->getTransitionName() === PaymentOrderStates::ACTION_REFUND));

        $transactionState = new \ArrayObject(['active' => false]);
        $eventDispatcher = static::createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(static function (object $event) use ($transactionState): object {
                static::assertFalse($transactionState->offsetGet('active'));
                static::assertInstanceOf(PaymentRefundFinishedEvent::class, $event);

                return $event;
            });

        $locks = new \ArrayObject();
        $rowLock = static::createStub(PaymentOrderRowLock::class);
        $rowLock->method('transaction')->willReturnCallback(
            static function (\Closure $write) use ($transactionState): mixed {
                $transactionState->offsetSet('active', true);

                try {
                    return $write();
                } finally {
                    $transactionState->offsetSet('active', false);
                }
            }
        );
        $rowLock->method('lockRefund')->willReturnCallback(static function () use ($locks): bool {
            $locks->append('refund');

            return false;
        });
        $rowLock->method('lockOrder')->willReturnCallback(static function () use ($locks): array {
            $locks->append('order');

            return ['paidAmount' => 1000, 'refundedAmount' => 0];
        });

        $this->refundService(
            $refundRepository,
            $this->gateway(null),
            $this->orderService($this->orderRepository($this->paidOrder()), $orderStateMachine, $rowLock),
            $refundStateMachine,
            $eventDispatcher,
            rowLock: $rowLock
        )->applyNotification($refund, $this->paidOrder(), new NotificationResult(true, true, outRefundId: 'channel-refund-1', amount: 1000, raw: ['status' => 'done']));

        static::assertSame(['refund', 'order'], $locks->getArrayCopy());
    }

    public function testApplyNotificationAnnouncesTheRefundWithItsOwnSubject(): void
    {
        $refund = $this->refundEntity();
        $order = $this->paidOrder();
        $order->notifyUrl = 'https://app.example.test/webhook';

        $refundRepository = static::createMock(EntityRepository::class);
        $refundRepository->method('search')->willReturn($this->refundResult($refund));
        $refundRepository->expects($this->once())->method('update');

        $delivery = new PaymentWebhookDeliveryEntity();
        $delivery->setUniqueIdentifier('delivery-id');
        $delivery->id = 'delivery-id';
        $delivery->paymentOrderId = 'order-id';
        $delivery->paymentAppId = 'app-id';
        $delivery->event = PaymentWebhookDispatcher::EVENT_REFUND_FINISHED;
        $delivery->subject = 'refund-id';
        $delivery->url = 'https://app.example.test/webhook';

        $deliveryRepository = static::createMock(EntityRepository::class);
        $deliveryRepository->expects($this->once())
            ->method('create')
            ->with(static::callback(static function (array $data): bool {
                // the refund id is the subject, so a second refund of the same order is announced too
                static::assertSame(PaymentWebhookDispatcher::EVENT_REFUND_FINISHED, $data[0]['event']);
                static::assertSame('refund-id', $data[0]['subject']);
                static::assertSame('order-id', $data[0]['paymentOrderId']);

                return true;
            }));
        $deliveryRepository->method('search')->willReturnOnConsecutiveCalls(
            new EntitySearchResult('payment_webhook_delivery', 0, new PaymentWebhookDeliveryCollection([]), null, new Criteria(), Context::createDefaultContext()),
            new EntitySearchResult('payment_webhook_delivery', 1, new PaymentWebhookDeliveryCollection([$delivery]), null, new Criteria(), Context::createDefaultContext())
        );

        $this->refundService(
            $refundRepository,
            $this->gateway(null),
            $this->orderService($this->orderRepository($order)),
            static::createStub(StateMachineRegistry::class),
            null,
            $deliveryRepository,
            $this->appRepository()
        )->applyNotification($refund, $order, new NotificationResult(true, true, outRefundId: 'channel-refund-1', amount: 1000, raw: ['status' => 'done']));
    }

    public function testAConfirmedSubmissionRejectionFailsTheRefund(): void
    {
        $refund = $this->refundEntity();
        $repository = new StaticEntityRepository([
            new PaymentRefundCollection(),
            new PaymentRefundCollection(),
            new PaymentRefundCollection([$refund]),
        ]);
        $rejection = PaymentRefundRejectedException::rejected('alipay_h5', 'definitively rejected');
        $gateway = static::createStub(GatewayInterface::class);
        $gateway->method('execute')->willThrowException($rejection);
        $states = static::createMock(StateMachineRegistry::class);
        $states->expects($this->once())->method('transition')->with(static::callback(
            static fn (Transition $transition): bool => $transition->getTransitionName() === PaymentRefundStates::ACTION_FAIL
        ));
        $service = $this->refundService($repository, $gateway, refundStateMachine: $states);

        $this->expectExceptionObject(PaymentGatewayException::gatewayError('alipay_h5', $rejection->getMessage()));

        $service->refund($this->app(), $this->paidOrder(), $this->refundRequest(1000));
    }

    public function testAnUnrecognizedRefundResultCannotBookAMovementOfMoney(): void
    {
        $refund = $this->refundEntity();
        $repository = new StaticEntityRepository([
            new PaymentRefundCollection(),
            new PaymentRefundCollection(),
            new PaymentRefundCollection([$refund]),
        ]);
        $states = static::createMock(StateMachineRegistry::class);
        $states->expects($this->never())->method('transition');
        $service = $this->refundService($repository, $this->gateway('unrecognized'), refundStateMachine: $states);

        $this->expectExceptionObject(PaymentGatewayException::gatewayResultMissing('alipay_h5'));

        $service->refund($this->app(), $this->paidOrder(), $this->refundRequest(1000));
    }

    public function testReconciliationKeepsAnUnknownRefundReservedWithoutSubmittingAgain(): void
    {
        $refund = $this->refundEntity();
        $repository = new StaticEntityRepository([new PaymentRefundCollection([$refund])]);
        $gateway = static::createMock(GatewayInterface::class);
        $gateway->expects($this->once())->method('execute')->willReturnCallback(static function (object $request): void {
            static::assertInstanceOf(RefundQuery::class, $request);
            $model = $request->getModel();
            static::assertInstanceOf(PaymentGatewayModel::class, $model);
            $model->offsetSet(PaymentGatewayModel::RESULT, new RefundResult(PaymentRefundStates::STATE_PROCESSING));
        });
        $states = static::createMock(StateMachineRegistry::class);
        $states->expects($this->never())->method('transition');

        $result = $this->refundService($repository, $gateway, refundStateMachine: $states)
            ->reconcile($this->app(), $this->paidOrder(), $refund);

        static::assertNull($result->refundedAt);
        static::assertCount(1, $repository->updates);
        static::assertSame('refund-id', $repository->updates[0][0]['id']);
    }

    public function testReconciliationReleasesTheReservationOnlyForAConfirmedFailure(): void
    {
        $refund = $this->refundEntity();
        $repository = new StaticEntityRepository([new PaymentRefundCollection([$refund])]);
        $gateway = static::createStub(GatewayInterface::class);
        $gateway->method('execute')->willThrowException(PaymentRefundRejectedException::rejected('alipay_h5', 'closed'));
        $states = static::createMock(StateMachineRegistry::class);
        $states->expects($this->once())->method('transition')->with(static::callback(
            static fn (Transition $transition): bool => $transition->getTransitionName() === PaymentRefundStates::ACTION_FAIL
        ));

        $this->refundService($repository, $gateway, refundStateMachine: $states)
            ->reconcile($this->app(), $this->paidOrder(), $refund);

        static::assertCount(1, $repository->updates);
        static::assertStringContainsString('closed', $repository->updates[0][0]['responsePayload']['exception']);
    }

    public function testReconciliationFailureCannotOverwriteAConcurrentSettlement(): void
    {
        $refund = $this->refundEntity();
        $repository = new StaticEntityRepository([new PaymentRefundCollection([$refund])]);
        $gateway = static::createStub(GatewayInterface::class);
        $gateway->method('execute')->willThrowException(PaymentRefundRejectedException::rejected('alipay_h5', 'closed'));
        $states = static::createMock(StateMachineRegistry::class);
        $states->expects($this->never())->method('transition');

        $this->refundService($repository, $gateway, refundStateMachine: $states, rowLock: $this->rowLock(settled: true))
            ->reconcile($this->app(), $this->paidOrder(), $refund);

        static::assertSame([], $repository->updates);
    }

    public function testReconciliationBooksAnAcceptedRefundAfterTheSubmissionResponseWasLost(): void
    {
        $refund = $this->refundEntity();
        $repository = new StaticEntityRepository([
            new PaymentRefundCollection([$refund]),
            new PaymentRefundCollection([$refund]),
        ]);
        $gateway = static::createStub(GatewayInterface::class);
        $gateway->method('execute')->willReturnCallback(static function (object $request): void {
            static::assertInstanceOf(RefundQuery::class, $request);
            $model = $request->getModel();
            static::assertInstanceOf(PaymentGatewayModel::class, $model);
            $model->offsetSet(PaymentGatewayModel::RESULT, new RefundResult(PaymentRefundStates::STATE_FINISHED, 'remote-refund'));
        });
        $states = static::createMock(StateMachineRegistry::class);
        $states->expects($this->once())->method('transition')->with(static::callback(
            static fn (Transition $transition): bool => $transition->getTransitionName() === PaymentRefundStates::ACTION_FINISH
        ));

        $this->refundService($repository, $gateway, refundStateMachine: $states)
            ->reconcile($this->app(), $this->paidOrder(), $refund);

        static::assertSame('remote-refund', $repository->updates[0][0]['outRefundId']);
        static::assertInstanceOf(\DateTimeInterface::class, $repository->updates[0][0]['refundedAt']);
    }

    /**
     * @param EntityRepository<PaymentRefundCollection> $refundRepository
     * @param EntityRepository<PaymentWebhookDeliveryCollection>|null $deliveryRepository
     * @param EntityRepository<PaymentAppCollection>|null $appRepository
     * @param list<PaymentCapability> $capabilities
     * @param list<PaymentCapability>|null $productCapabilities
     */
    private function refundService(
        EntityRepository $refundRepository,
        GatewayInterface $gateway,
        ?PaymentOrderService $orderService = null,
        ?StateMachineRegistry $refundStateMachine = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        ?EntityRepository $deliveryRepository = null,
        ?EntityRepository $appRepository = null,
        ?PaymentOrderRowLock $rowLock = null,
        array $capabilities = self::REFUND_CAPABILITIES,
        ?array $productCapabilities = null
    ): PaymentRefundService {
        $rowLock ??= $this->rowLock();

        $initialStateIdLoader = static::createStub(InitialStateIdLoader::class);
        $initialStateIdLoader->method('get')->willReturn('state-open');

        $cipher = new PaymentCredentialCipher('test-secret');

        if (!$deliveryRepository instanceof EntityRepository) {
            $deliveryRepository = static::createStub(EntityRepository::class);
            $deliveryRepository->method('search')->willReturn(new EntitySearchResult(
                'payment_webhook_delivery',
                0,
                new PaymentWebhookDeliveryCollection([]),
                null,
                new Criteria(),
                Context::createDefaultContext()
            ));
        }

        if (!$appRepository instanceof EntityRepository) {
            $appRepository = static::createStub(EntityRepository::class);
            $appRepository->method('search')->willReturn(new EntitySearchResult(
                'payment_app',
                0,
                new PaymentAppCollection([]),
                null,
                new Criteria(),
                Context::createDefaultContext()
            ));
        }

        $webhookService = new PaymentWebhookDispatcher(
            $deliveryRepository,
            $appRepository,
            new PaymentWebhookSigner(new PaymentSignatureService(), $cipher, new MockClock(self::NOW)),
            static::createStub(HttpClientInterface::class),
            new MockClock(self::NOW),
            static::createStub(LoggerInterface::class),
            static::createStub(Connection::class)
        );

        return new PaymentRefundService(
            $refundRepository,
            $this->configResolver($gateway, $capabilities, $productCapabilities),
            $orderService ?? $this->orderService($this->orderRepository($this->paidOrder()), null, $rowLock),
            new PaymentEventDispatcher(
                StaticEntityRepository::of(PaymentOrderCollection::class),
                StaticEntityRepository::of(PaymentRefundCollection::class),
                $webhookService,
                $eventDispatcher ?? static::createStub(EventDispatcherInterface::class),
                new MockClock(self::NOW),
                static::createStub(LoggerInterface::class),
            ),
            new PaymentNotifyUrlBuilder(static::createStub(RequestStack::class), 'https://base.example.test'),
            new PaymentRefundStateHandler($refundStateMachine ?? static::createStub(StateMachineRegistry::class)),
            $initialStateIdLoader,
            new MockClock(self::NOW),
            $this->numberRanges(),
            $rowLock
        );
    }

    /**
     * @param array{paidAmount: int, refundedAmount: int} $amounts
     */
    private function rowLock(
        array $amounts = ['paidAmount' => 1000, 'refundedAmount' => 0],
        int $reserved = 0,
        bool $settled = false
    ): PaymentOrderRowLock {
        $rowLock = static::createStub(PaymentOrderRowLock::class);
        $rowLock->method('transaction')->willReturnCallback(static fn (\Closure $write): mixed => $write());
        $rowLock->method('lockOrder')->willReturn($amounts);
        $rowLock->method('lockRefund')->willReturn($settled);
        $rowLock->method('reservedRefundAmount')->willReturn($reserved);

        return $rowLock;
    }

    private function numberRanges(): AbstractNumberRangeValueGenerator
    {
        $generator = static::createStub(AbstractNumberRangeValueGenerator::class);
        $generator->method('getValue')->willReturn('PR1001');

        return $generator;
    }

    private function gateway(?string $status, ?string $outRefundId = null): GatewayInterface
    {
        $gateway = static::createStub(GatewayInterface::class);
        $gateway->method('execute')->willReturnCallback(static function (object $request) use ($status, $outRefundId): void {
            static::assertInstanceOf(RefundRequest::class, $request);

            $model = $request->getModel();
            static::assertInstanceOf(PaymentGatewayModel::class, $model);

            $model->offsetSet(PaymentGatewayModel::REQUEST, ['out_refund_no' => 'app-refund-1']);
            $model->offsetSet(PaymentGatewayModel::RESPONSE, ['code' => 'ok']);

            if ($status !== null) {
                $model->offsetSet(PaymentGatewayModel::RESULT, new RefundResult($status, $outRefundId));
            }
        });

        return $gateway;
    }

    private function failingGateway(): GatewayInterface
    {
        $gateway = static::createStub(GatewayInterface::class);
        $gateway->method('execute')->willThrowException(new \RuntimeException('boom'));

        return $gateway;
    }

    /**
     * @param list<PaymentCapability> $capabilities
     * @param list<PaymentCapability>|null $productCapabilities
     */
    private function configResolver(GatewayInterface $gateway, array $capabilities = self::REFUND_CAPABILITIES, ?array $productCapabilities = null): PaymentConfigResolver
    {
        $channel = new PaymentProduct(
            technicalName: 'alipay_h5',
            providerTechnicalName: 'alipay',
            method: 'alipay',
            label: 'Alipay H5',
            interactions: [PaymentInteraction::REDIRECT],
            capabilities: $productCapabilities ?? $capabilities,
        );

        $gatewayFactory = static::createStub(PaymentGatewayFactory::class);
        $gatewayFactory->method('createForPayment')->willReturn($gateway);

        $provider = new PaymentProvider(
            technicalName: 'alipay',
            label: 'Alipay',
            credentials: [],
            products: ['alipay_h5' => $channel],
            gatewayFactory: $gatewayFactory,
            notificationExtractor: static::createStub(PaymentNotificationExtractorInterface::class),
            capabilities: $capabilities,
        );

        $resolved = new ResolvedPaymentConfig($provider, $channel, [], [], [], [], 'account-id');
        $resolver = static::createStub(PaymentConfigResolver::class);
        $resolver->method('resolveForOrder')->willReturn($resolved);
        $resolver->method('resolveForAttempt')->willReturn($resolved);

        return $resolver;
    }

    /**
     * @param EntityRepository<PaymentOrderCollection>|null $orderRepository
     */
    private function orderService(
        ?EntityRepository $orderRepository = null,
        ?StateMachineRegistry $stateMachine = null,
        ?PaymentOrderRowLock $rowLock = null
    ): PaymentOrderService {
        $initialStateIdLoader = static::createStub(InitialStateIdLoader::class);
        $initialStateIdLoader->method('get')->willReturn(self::CREATED_STATE);

        return new PaymentOrderService(
            $orderRepository ?? $this->orderRepository($this->paidOrder()),
            $initialStateIdLoader,
            new PaymentOrderStateHandler($stateMachine ?? static::createStub(StateMachineRegistry::class)),
            new MockClock(self::NOW),
            NumberRangeFixture::generator('PG1001'),
            $rowLock ?? $this->rowLock(),
            StaticEntityRepository::of(PaymentAttemptCollection::class),
        );
    }

    /**
     * @return EntityRepository<PaymentOrderCollection>
     */
    private function orderRepository(PaymentOrderEntity ...$orders): EntityRepository
    {
        $repository = static::createStub(EntityRepository::class);
        $repository->method('search')->willReturn(new EntitySearchResult(
            PaymentOrderEntity::ENTITY_NAME,
            \count($orders),
            new PaymentOrderCollection($orders),
            null,
            new Criteria(),
            Context::createDefaultContext()
        ));

        return $repository;
    }

    /**
     * @return EntitySearchResult<PaymentRefundCollection>
     */
    private function refundResult(PaymentRefundEntity ...$refunds): EntitySearchResult
    {
        return new EntitySearchResult(
            PaymentRefundEntity::ENTITY_NAME,
            \count($refunds),
            new PaymentRefundCollection($refunds),
            null,
            new Criteria(),
            Context::createDefaultContext()
        );
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function refundEntity(array $overrides = []): PaymentRefundEntity
    {
        $refund = new PaymentRefundEntity();
        $refund->setUniqueIdentifier('refund-id');
        $refund->id = 'refund-id';
        $refund->refundNo = 'PR0001';
        $refund->paymentOrderId = 'order-id';
        $refund->paymentAttemptId = 'attempt-id';
        $refund->outRefundNo = 'app-refund-1';
        $refund->amount = 1000;
        $refund->currency = 'CNY';
        $refund->stateId = 'state-open';

        if (\array_key_exists('refundedAt', $overrides)) {
            $refundedAt = $overrides['refundedAt'];
            static::assertInstanceOf(\DateTimeInterface::class, $refundedAt);
            $refund->refundedAt = $refundedAt;
        }

        if (\array_key_exists('amount', $overrides)) {
            $refund->amount = (int) $overrides['amount'];
        }

        return $refund;
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function order(array $overrides = []): PaymentOrderEntity
    {
        $order = new PaymentOrderEntity();
        $order->setUniqueIdentifier('order-id');
        $order->id = 'order-id';
        $order->paymentNo = 'PG0001';
        $order->outTradeNo = 'app-trade-1';
        $order->paymentAppId = 'app-id';
        $attempt = new PaymentAttemptEntity();
        $attempt->id = 'attempt-id';
        $attempt->paymentOrderId = $order->id;
        $attempt->attemptNo = 'PG0001';
        $attempt->accountId = 'account-id';
        $attempt->providerTechnicalName = 'alipay';
        $attempt->channelTechnicalName = 'alipay_h5';
        $attempt->status = PaymentAttemptEntity::STATUS_HANDOVER;
        $order->activeAttempt = $attempt;
        $order->activeAttemptId = $attempt->id;
        $order->amount = 1000;
        $order->currency = 'CNY';
        $order->subject = 'An order';
        $order->stateId = self::CREATED_STATE;

        if (\array_key_exists('stateId', $overrides)) {
            $order->stateId = (string) $overrides['stateId'];
        }

        if (\array_key_exists('paidAt', $overrides)) {
            $paidAt = $overrides['paidAt'];
            static::assertInstanceOf(\DateTimeInterface::class, $paidAt);
            $order->paidAt = $paidAt;
        }

        if (\array_key_exists('paidAmount', $overrides)) {
            $order->paidAmount = (int) $overrides['paidAmount'];
        }

        if (\array_key_exists('refundedAmount', $overrides)) {
            $order->refundedAmount = (int) $overrides['refundedAmount'];
        }

        if (\array_key_exists('outPaymentNo', $overrides)) {
            $order->outPaymentNo = (string) $overrides['outPaymentNo'];
        }

        return $order;
    }

    private function paidOrder(): PaymentOrderEntity
    {
        return $this->order([
            'paidAt' => new \DateTimeImmutable(self::NOW),
            'paidAmount' => 1000,
            'refundedAmount' => 0,
            'outPaymentNo' => 'channel-trade-1',
            'stateId' => 'state-paid',
        ]);
    }

    /**
     * @return EntityRepository<PaymentAppCollection>
     */
    private function appRepository(): EntityRepository
    {
        $app = $this->app();
        $app->appSecret = (new PaymentCredentialCipher('test-secret'))->encryptValue('secret');

        $repository = static::createStub(EntityRepository::class);
        $repository->method('search')->willReturn(new EntitySearchResult(
            'payment_app',
            1,
            new PaymentAppCollection([$app]),
            null,
            new Criteria(),
            Context::createDefaultContext()
        ));

        return $repository;
    }

    private function app(): PaymentAppEntity
    {
        $app = new PaymentAppEntity();
        $app->setUniqueIdentifier('app-id');
        $app->id = 'app-id';
        $app->technicalName = 'demo-app';
        $app->appSecret = 'secret';
        $app->active = true;

        return $app;
    }

    private function refundRequest(int $amount): PaymentRefundRequest
    {
        return new PaymentRefundRequest('app-refund-1', $amount);
    }
}
