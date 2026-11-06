<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Notification;

use Doctrine\DBAL\Connection;
use Payum\Core\GatewayInterface;
use Payum\Core\Request\Notify;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\App\PaymentAppCollection;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\App\PaymentCredentialCipher;
use Shopwell\Core\System\Payment\App\PaymentSignatureService;
use Shopwell\Core\System\Payment\Exception\PaymentNotificationException;
use Shopwell\Core\System\Payment\Exception\PaymentOrderException;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayFactory;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\Gateway\Request\NotificationResult;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationCollection;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationEntity;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationExtractorInterface;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationProcessor;
use Shopwell\Core\System\Payment\Notification\PaymentNotifyUrlBuilder;
use Shopwell\Core\System\Payment\Notification\Struct\PaymentNotificationData;
use Shopwell\Core\System\Payment\Order\Aggregate\PaymentAttempt\PaymentAttemptCollection;
use Shopwell\Core\System\Payment\Order\Aggregate\PaymentAttempt\PaymentAttemptEntity;
use Shopwell\Core\System\Payment\Order\Aggregate\PaymentRefund\PaymentRefundCollection;
use Shopwell\Core\System\Payment\Order\Aggregate\PaymentRefund\PaymentRefundEntity;
use Shopwell\Core\System\Payment\Order\Event\PaymentOrderPaidEvent;
use Shopwell\Core\System\Payment\Order\Event\PaymentRefundFinishedEvent;
use Shopwell\Core\System\Payment\Order\PaymentOrderCollection;
use Shopwell\Core\System\Payment\Order\PaymentOrderEntity;
use Shopwell\Core\System\Payment\Order\PaymentOrderPaidHandler;
use Shopwell\Core\System\Payment\Order\PaymentOrderRowLock;
use Shopwell\Core\System\Payment\Order\PaymentOrderService;
use Shopwell\Core\System\Payment\Order\PaymentOrderStateHandler;
use Shopwell\Core\System\Payment\Order\PaymentOrderStates;
use Shopwell\Core\System\Payment\Order\PaymentRefundService;
use Shopwell\Core\System\Payment\Order\PaymentRefundStateHandler;
use Shopwell\Core\System\Payment\Order\PaymentRefundStates;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Product\PaymentInteraction;
use Shopwell\Core\System\Payment\Product\PaymentProduct;
use Shopwell\Core\System\Payment\Provider\Config\PaymentConfigResolver;
use Shopwell\Core\System\Payment\Provider\PaymentProvider;
use Shopwell\Core\System\Payment\Provider\PaymentProviderRegistry;
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
use Shopwell\Tests\Unit\Core\System\Payment\PaymentEventDispatcherFixture;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentNotificationProcessor::class)]
class PaymentNotificationProcessorTest extends TestCase
{
    private const NOW = '2024-01-01T00:00:00+00:00';

    private const CREATED_STATE = 'state-created';

    public function testProcessRejectsANotificationOfAnUnknownOrder(): void
    {
        $notificationRepository = $this->notificationRepository(
            onCreate: function (array $row): void {
                static::assertSame('alipay', $row['providerTechnicalName']);
                static::assertSame('raw-body', $row['rawBody']);
                static::assertSame('1.2.3.4', $row['remoteIp']);
                // credentials never end up on the notification
                static::assertSame('[hidden]', $row['headers']['authorization']);
                static::assertSame('value', $row['headers']['x-custom']);
            },
            onUpdate: function (array $row): void {
                static::assertSame(PaymentNotificationEntity::RESULT_UNMATCHED, $row['processResult']);
                static::assertNull($row['paymentOrderId']);
                static::assertFalse($row['signatureValid']);
                static::assertFalse($row['processed']);
            }
        );

        $service = $this->service(
            $notificationRepository,
            static::createStub(GatewayInterface::class),
            $this->orderRepository(),
            $this->extractor('PG0001'),
            knownAttempt: false,
        );

        $this->expectExceptionObject(PaymentNotificationException::notificationOrderUnknown('alipay', 'PG0001'));

        $service->process('alipay', 'raw-body', ['Authorization' => 'Bearer secret', 'X-Custom' => 'value'], '1.2.3.4');
    }

    public function testTheUrlCannotReplaceTheSignedPaymentReference(): void
    {
        $orderRepository = new StaticEntityRepository([new PaymentOrderCollection([$this->order()])]);
        $eventDispatcher = static::createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->never())->method('dispatch');
        $service = $this->service(
            $this->notificationRepository(),
            $this->gateway(new NotificationResult(signatureValid: true, succeeded: true, amount: 1000, currency: 'CNY', paymentNo: 'PG0002')),
            $orderRepository,
            $this->extractor('PG0002'),
            eventDispatcher: $eventDispatcher,
        );
        $this->expectExceptionObject(PaymentNotificationException::notificationInvalid('alipay', 'the signed payment reference does not match the payment'));

        try {
            $service->process('alipay', 'signed-payment-two', [], null, 'PG0001');
        } finally {
            static::assertSame([], $orderRepository->updates);
        }
    }

    public function testTheUrlIsNotProofOfAMissingSignedPaymentReference(): void
    {
        $orderRepository = new StaticEntityRepository([new PaymentOrderCollection([$this->order()])]);
        $service = $this->service(
            $this->notificationRepository(),
            $this->gateway(new NotificationResult(signatureValid: true, succeeded: true, amount: 1000, currency: 'CNY')),
            $orderRepository,
            $this->extractor(null),
        );
        $this->expectExceptionObject(PaymentNotificationException::notificationInvalid('alipay', 'the signed payment reference does not match the payment'));

        $service->process('alipay', 'signed-body-without-payment-reference', [], null, 'PG0001');
    }

    public function testARefundWithoutASignedRefundReferenceCannotSettleAPayment(): void
    {
        $orderRepository = new StaticEntityRepository([new PaymentOrderCollection([$this->order()])]);
        $service = $this->service(
            $this->notificationRepository(),
            $this->gateway(new NotificationResult(signatureValid: true, succeeded: true, amount: 1000, currency: 'CNY', paymentNo: 'PG0001', isRefund: true)),
            $orderRepository,
            $this->extractor(null),
        );
        $this->expectExceptionObject(PaymentNotificationException::notificationInvalid('alipay', 'the signed refund does not belong to the payment'));

        $service->process('alipay', 'signed-refund-without-refund-reference', [], null, 'PG0001');
    }

    public function testASignedRefundOfAnotherPaymentCannotBeBooked(): void
    {
        $refund = $this->refund();
        $refund->paymentOrderId = 'another-order';
        $orderRepository = new StaticEntityRepository([new PaymentOrderCollection([$this->order()])]);
        $refundRepository = new StaticEntityRepository([new PaymentRefundCollection([$refund])]);
        $eventDispatcher = static::createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->never())->method('dispatch');
        $service = $this->service(
            $this->notificationRepository(),
            $this->gateway(new NotificationResult(signatureValid: true, succeeded: true, amount: 400, currency: 'CNY', refundNo: 'PR0001', isRefund: true)),
            $orderRepository,
            $this->extractor(null),
            eventDispatcher: $eventDispatcher,
            refundRepository: $refundRepository,
        );
        $this->expectExceptionObject(PaymentNotificationException::notificationInvalid('alipay', 'the signed refund does not belong to the payment'));

        try {
            $service->process('alipay', 'signed-refund-of-another-payment', [], null, 'PG0001');
        } finally {
            static::assertSame([], $orderRepository->updates);
            static::assertSame([], $refundRepository->updates);
        }
    }

    public function testSignedExternalReferenceUsesTheOriginalAttemptAndMerchantAccount(): void
    {
        $order = $this->order();
        $order->paymentNo = 'BUSINESS-OBLIGATION';
        $attempt = $this->paymentAttempt();
        $attempt->attemptNo = 'ORIGINAL-EXTERNAL-REQUEST';
        $attempt->accountId = 'original-merchant-account';
        $gateway = $this->gateway(new NotificationResult(paymentNo: $attempt->attemptNo, signatureValid: true, succeeded: false, amount: 1000));
        $resolved = $this->configResolver($gateway)->resolveForAttempt($this->app(), $order, $attempt);
        $resolver = static::createMock(PaymentConfigResolver::class);
        $resolver->expects($this->once())->method('resolveForAttempt')->with(
            static::isInstanceOf(PaymentAppEntity::class),
            static::callback(static fn (PaymentOrderEntity $value): bool => $value->paymentNo === 'BUSINESS-OBLIGATION'),
            static::callback(static fn (PaymentAttemptEntity $value): bool => $value->accountId === 'original-merchant-account'),
        )->willReturn($resolved);
        $resolver->expects($this->never())->method('resolveForOrder');
        $notificationRepository = $this->notificationRepository(onUpdate: static function (array $row): void {
            static::assertSame('attempt-id', $row['paymentAttemptId']);
            static::assertSame(PaymentNotificationEntity::RESULT_IGNORED, $row['processResult']);
        });
        $service = $this->service(
            $notificationRepository,
            $gateway,
            $this->orderRepository($order),
            $this->extractor($attempt->attemptNo),
            matchedAttempt: $attempt,
            configResolver: $resolver,
        );

        $service->process('alipay', 'signed-original-attempt', [], null, $attempt->attemptNo);
    }

    public function testAttemptFromAnotherProviderCannotSelectTheVerificationAccount(): void
    {
        $attempt = $this->paymentAttempt();
        $attempt->providerTechnicalName = 'wechat';
        $gateway = static::createMock(GatewayInterface::class);
        $gateway->expects($this->never())->method('execute');
        $service = $this->service(
            $this->notificationRepository(),
            $gateway,
            $this->orderRepository($this->order()),
            $this->extractor('PG0001'),
            matchedAttempt: $attempt,
        );

        $this->expectExceptionObject(PaymentNotificationException::notificationInvalid('alipay', 'the provider does not match the payment attempt'));
        $service->process('alipay', 'wrong-provider', [], null, 'PG0001');
    }

    public function testProcessMarksTheNotificationInvalidWhenTheSignatureIsNotValid(): void
    {
        $order = $this->order();

        $notificationRepository = $this->notificationRepository(
            onUpdate: function (array $row): void {
                static::assertSame(PaymentNotificationEntity::RESULT_INVALID_SIGNATURE, $row['processResult']);
                static::assertSame('order-id', $row['paymentOrderId']);
                static::assertFalse($row['signatureValid']);
            }
        );

        $service = $this->service(
            $notificationRepository,
            $this->gateway(new NotificationResult(paymentNo: 'PG0001', signatureValid: false, succeeded: false)),
            $this->orderRepository($order),
            $this->extractor('PG0001')
        );

        $this->expectExceptionObject(PaymentNotificationException::notificationInvalid('alipay', 'the signature of the notification is invalid'));

        $service->process('alipay', 'raw-body', [], '1.2.3.4');
    }

    public function testProcessRejectsANotificationWithAMismatchedAmount(): void
    {
        $order = $this->order();

        $notificationRepository = $this->notificationRepository(
            onUpdate: function (array $row): void {
                static::assertSame(PaymentNotificationEntity::RESULT_AMOUNT_MISMATCH, $row['processResult']);
                static::assertTrue($row['signatureValid']);
            }
        );

        $service = $this->service(
            $notificationRepository,
            $this->gateway(new NotificationResult(paymentNo: 'PG0001', signatureValid: true, succeeded: true, amount: 999)),
            $this->orderRepository($order),
            $this->extractor('PG0001')
        );

        $this->expectExceptionObject(PaymentOrderException::amountMismatch('PG0001', 1000, 999));

        $service->process('alipay', 'raw-body', [], '1.2.3.4');
    }

    public function testProcessRejectsANotificationWithAMismatchedCurrency(): void
    {
        $order = $this->order();

        $notificationRepository = $this->notificationRepository(
            onUpdate: function (array $row): void {
                // the notification is not applied at all: rejected, not half written, and it is
                // recorded as what it is - support filters by the result
                static::assertSame(PaymentNotificationEntity::RESULT_CURRENCY_MISMATCH, $row['processResult']);
                static::assertTrue($row['signatureValid']);
                static::assertFalse($row['processed']);
            }
        );

        [$orderRepository, $orderStateMachine, $eventDispatcher, $deliveryRepository] = $this->expectNoPaymentApplied($order);

        $service = $this->service(
            $notificationRepository,
            $this->gateway(new NotificationResult(paymentNo: 'PG0001', signatureValid: true, succeeded: true, amount: 1000, currency: 'JPY')),
            $orderRepository,
            $this->extractor('PG0001'),
            orderService: $this->orderService($orderRepository, $orderStateMachine),
            eventDispatcher: $eventDispatcher,
            deliveryRepository: $deliveryRepository
        );

        $this->expectExceptionObject(PaymentOrderException::currencyMismatch('PG0001', 'CNY', 'JPY'));

        $service->process('alipay', 'raw-body', [], '1.2.3.4');
    }

    public function testProcessAppliesANotificationWhoseCurrencyIsReportedInAnotherCase(): void
    {
        // `cny` and `CNY` are the same currency, so the case must not reject a real payment
        $this->assertTheNotificationIsApplied('cny');
    }

    public function testProcessAppliesANotificationWithoutAReportedCurrency(): void
    {
        // an interface that reports no currency cannot be disproved, and refusing the payment would
        // leave it unpaid forever while the notification keeps retrying
        $this->assertTheNotificationIsApplied(null);
    }

    public function testProcessIgnoresANotificationThatDidNotSucceed(): void
    {
        $order = $this->order();

        $notificationRepository = $this->notificationRepository(
            onUpdate: function (array $row): void {
                static::assertSame(PaymentNotificationEntity::RESULT_IGNORED, $row['processResult']);
                // an ignored notification is fully processed, it stays for support only
                static::assertTrue($row['processed']);
            }
        );

        // the handler must not be reached: nothing is written for a payment the channel did not confirm
        [$orderRepository, $orderStateMachine, $eventDispatcher, $deliveryRepository] = $this->expectNoPaymentApplied($order);

        $service = $this->service(
            $notificationRepository,
            $this->gateway(new NotificationResult(paymentNo: 'PG0001', signatureValid: true, succeeded: false, amount: 1000)),
            $orderRepository,
            $this->extractor('PG0001'),
            orderService: $this->orderService($orderRepository, $orderStateMachine),
            eventDispatcher: $eventDispatcher,
            deliveryRepository: $deliveryRepository
        );

        $result = $service->process('alipay', 'raw-body', [], '1.2.3.4');

        static::assertSame('notification-id', $result->id);
    }

    public function testProcessTreatsANotificationOfAnAlreadyPaidOrderAsDuplicate(): void
    {
        $notifyUrl = 'https://app.example.test/webhook';
        $order = $this->order(['paidAt' => new \DateTimeImmutable(self::NOW), 'paidAmount' => 1000, 'stateId' => 'state-paid', 'notifyUrl' => $notifyUrl]);

        $notificationRepository = $this->notificationRepository(
            onUpdate: function (array $row): void {
                static::assertSame(PaymentNotificationEntity::RESULT_DUPLICATE, $row['processResult']);
                static::assertTrue($row['processed']);
            }
        );

        // a duplicate does not write the payment again and does not migrate the order again, but it
        // does announce again: the announcement of the first processing may have failed, so every
        // duplicate is a retry window for exactly that
        $orderRepository = static::createMock(EntityRepository::class);
        $orderRepository->method('search')->willReturn($this->orderSearchResult($order));
        $orderRepository->expects($this->never())->method('update');

        $orderStateMachine = static::createMock(StateMachineRegistry::class);
        $orderStateMachine->expects($this->never())->method('transition');

        $eventDispatcher = static::createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(static::isInstanceOf(PaymentOrderPaidEvent::class));

        // the announce() of the duplicate finds the delivery of the first announce, so it returns it
        // instead of queueing a second one
        $deliveryRepository = $this->deliveryRepositoryWithExistingDelivery();

        $service = $this->service(
            $notificationRepository,
            $this->gateway(new NotificationResult(paymentNo: 'PG0001', signatureValid: true, succeeded: true, amount: 1000)),
            $orderRepository,
            $this->extractor('PG0001'),
            orderService: $this->orderService($orderRepository, $orderStateMachine),
            eventDispatcher: $eventDispatcher,
            deliveryRepository: $deliveryRepository,
            appRepository: $this->appRepository()
        );

        $result = $service->process('alipay', 'raw-body', [], '1.2.3.4');

        static::assertSame('notification-id', $result->id);
    }

    public function testProcessMarksTheOrderPaidAndNotifiesTheApp(): void
    {
        $notifyUrl = 'https://app.example.test/webhook';
        $order = $this->order(['notifyUrl' => $notifyUrl]);
        $paidOrder = $this->order(['paidAt' => new \DateTimeImmutable(self::NOW), 'paidAmount' => 1000, 'outPaymentNo' => 'channel-trade-1', 'stateId' => 'state-paid', 'notifyUrl' => $notifyUrl]);

        $notificationRepository = $this->notificationRepository(
            onUpdate: function (array $row): void {
                // a signed notification with a matching amount on an unpaid order is a successful
                // processing, not an error
                static::assertSame(PaymentNotificationEntity::RESULT_OK, $row['processResult']);
                static::assertTrue($row['processed']);
                static::assertSame('order-id', $row['paymentOrderId']);
                static::assertTrue($row['signatureValid']);
                static::assertNull($row['errorMessage']);
            }
        );

        $orderRepository = static::createMock(EntityRepository::class);
        $orderRepository->expects($this->exactly(2))
            ->method('search')
            ->willReturnOnConsecutiveCalls(
                $this->orderSearchResult($order),
                $this->orderSearchResult($paidOrder)
            );
        // the handler is called exactly once: it is the only writer, and it gets the amount, the
        // channel trade number and the raw answer the notification reported
        $orderRepository->expects($this->once())
            ->method('update')
            ->with(static::callback(static function (array $data): bool {
                $row = $data[0];

                static::assertSame('order-id', $row['id']);
                static::assertSame(1000, $row['paidAmount']);
                static::assertSame('channel-trade-1', $row['outPaymentNo']);
                static::assertSame(['trade' => 'raw'], $row['channelData']);
                static::assertInstanceOf(\DateTimeInterface::class, $row['paidAt']);

                return true;
            }));

        $transitions = [];
        $orderStateMachine = static::createMock(StateMachineRegistry::class);
        $orderStateMachine->expects($this->exactly(2))
            ->method('transition')
            ->with(static::callback(static function (Transition $transition) use (&$transitions): bool {
                $transitions[] = $transition->getTransitionName();

                return \in_array($transition->getTransitionName(), [PaymentOrderStates::ACTION_PROCESS, PaymentOrderStates::ACTION_PAY], true);
            }));

        $eventDispatcher = static::createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(static::isInstanceOf(PaymentOrderPaidEvent::class));

        $deliveryRepository = static::createMock(EntityRepository::class);
        $deliveryRepository->expects($this->once())
            ->method('create')
            ->with(static::callback(function (array $data) use ($notifyUrl): bool {
                $row = $data[0];

                static::assertSame('order-id', $row['paymentOrderId']);
                static::assertSame(PaymentWebhookDispatcher::EVENT_ORDER_PAID, $row['event']);
                static::assertSame($notifyUrl, $row['url']);
                static::assertSame(PaymentWebhookDeliveryEntity::STATUS_PENDING, $row['status']);

                static::assertSame('PG0001', $row['payload']['payment_no']);
                static::assertSame(PaymentOrderStates::STATE_PAID, $row['payload']['data']['state']);
                static::assertSame(1000, $row['payload']['data']['amount']);
                static::assertSame('channel-trade-1', $row['payload']['data']['out_payment_no']);

                return true;
            }));

        $delivery = new PaymentWebhookDeliveryEntity();
        $delivery->setUniqueIdentifier('delivery-id');
        $delivery->id = 'delivery-id';
        $delivery->paymentOrderId = 'order-id';
        $delivery->paymentAppId = 'app-id';
        $delivery->event = PaymentWebhookDispatcher::EVENT_ORDER_PAID;
        $delivery->url = $notifyUrl;

        // the lookup for an already queued delivery finds none, the re-read after the write finds it
        $deliveryRepository->method('search')->willReturnOnConsecutiveCalls(
            $this->deliverySearchResult(),
            $this->deliverySearchResult($delivery)
        );

        $service = $this->service(
            $notificationRepository,
            $this->gateway(new NotificationResult(paymentNo: 'PG0001', signatureValid: true, succeeded: true, outPaymentNo: 'channel-trade-1', amount: 1000, raw: ['trade' => 'raw'])),
            $orderRepository,
            $this->extractor('PG0001'),
            orderService: $this->orderService($orderRepository, $orderStateMachine),
            eventDispatcher: $eventDispatcher,
            deliveryRepository: $deliveryRepository,
            appRepository: $this->appRepository()
        );

        static::assertSame('notification-id', $service->process('alipay', 'raw-body', [], '1.2.3.4')->id);

        // the order was opened first and paid afterwards, which is the order the state machine demands
        static::assertSame([PaymentOrderStates::ACTION_PROCESS, PaymentOrderStates::ACTION_PAY], $transitions);
    }

    /**
     * @return iterable<string, array{int, bool}>
     */
    public static function refundNotificationProvider(): iterable
    {
        yield 'plain refund' => [400, false];
        yield 'encrypted partial refund' => [400, true];
        yield 'encrypted full refund' => [1000, true];
    }

    #[DataProvider('refundNotificationProvider')]
    public function testProcessMarksASuccessfulRefundNotificationAsProcessed(int $amount, bool $encrypted): void
    {
        $order = $this->order(['paidAt' => new \DateTimeImmutable(self::NOW), 'paidAmount' => 1000, 'stateId' => 'state-paid']);
        $refund = $this->refund();
        $refund->amount = $amount;

        // the repository expectation doubles as the proof that conclude() ran: the outcome of the
        // processing is written exactly once, in the finally block of process()
        $notificationRepository = $this->notificationRepository(
            onUpdate: function (array $row): void {
                static::assertSame(PaymentNotificationEntity::RESULT_OK, $row['processResult']);
                static::assertTrue($row['processed']);
                static::assertSame('order-id', $row['paymentOrderId']);
                static::assertTrue($row['signatureValid']);
                static::assertNull($row['errorMessage']);
            }
        );

        $refundRepository = static::createMock(EntityRepository::class);
        $refundRepository->method('search')->willReturn(new EntitySearchResult(
            PaymentRefundEntity::ENTITY_NAME,
            1,
            new PaymentRefundCollection([$refund]),
            null,
            new Criteria(),
            Context::createDefaultContext()
        ));
        // a confirmed refund is booked as finished on the refund itself
        $refundRepository->expects($this->once())
            ->method('update')
            ->with(static::callback(static function (array $data): bool {
                $row = $data[0];

                static::assertSame('refund-id', $row['id']);
                static::assertSame('channel-refund-1', $row['outRefundId']);
                static::assertInstanceOf(\DateTimeInterface::class, $row['refundedAt']);

                return true;
            }));

        $orderRepository = static::createMock(EntityRepository::class);
        $orderRepository->method('search')->willReturn($this->orderSearchResult($order));
        // and the settled amount is booked on the order
        $orderRepository->expects($this->once())
            ->method('update')
            ->with(static::callback(static function (array $data) use ($amount): bool {
                static::assertSame($amount, $data[0]['refundedAmount']);

                return true;
            }));

        $refundStateMachine = static::createMock(StateMachineRegistry::class);
        $refundStateMachine->expects($this->once())
            ->method('transition')
            ->with(static::callback(static fn (Transition $transition): bool => $transition->getTransitionName() === PaymentRefundStates::ACTION_FINISH));

        $eventDispatcher = static::createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(static::isInstanceOf(PaymentRefundFinishedEvent::class));

        $service = $this->service(
            $notificationRepository,
            $this->gateway(new NotificationResult(paymentNo: 'PG0001', signatureValid: true, succeeded: true, outRefundId: 'channel-refund-1', amount: $amount, refundNo: 'PR0001', isRefund: true)),
            $orderRepository,
            $encrypted ? $this->extractor(null) : $this->refundExtractor('PR0001'),
            eventDispatcher: $eventDispatcher,
            refundRepository: $refundRepository,
            refundStateMachine: $refundStateMachine
        );

        static::assertSame('notification-id', $service->process('alipay', 'raw-body', [], '1.2.3.4', $encrypted ? 'PG0001' : null)->id);
    }

    public function testProcessThrowsWhenTheStoredNotificationCannotBeReloaded(): void
    {
        $order = $this->order(['paidAt' => new \DateTimeImmutable(self::NOW), 'stateId' => 'state-paid']);

        $notificationRepository = static::createStub(EntityRepository::class);
        $notificationRepository->method('create');
        $notificationRepository->method('update');
        $notificationRepository->method('search')->willReturn(new EntitySearchResult(
            'payment_notification',
            0,
            new PaymentNotificationCollection([]),
            null,
            new Criteria(),
            Context::createDefaultContext()
        ));

        $service = $this->service(
            $notificationRepository,
            $this->gateway(new NotificationResult(paymentNo: 'PG0001', signatureValid: true, succeeded: true, amount: 1000)),
            $this->orderRepository($order),
            $this->extractor('PG0001')
        );

        try {
            $service->process('alipay', 'raw-body', [], '1.2.3.4');
            static::fail('the process call should have thrown');
        } catch (PaymentException $exception) {
            static::assertSame(PaymentException::NOTIFICATION_NOT_FOUND, $exception->getErrorCode());
        }
    }

    /**
     * @param EntityRepository<PaymentNotificationCollection> $notificationRepository
     * @param EntityRepository<PaymentOrderCollection>|null $orderRepository
     * @param EntityRepository<PaymentWebhookDeliveryCollection>|null $deliveryRepository
     * @param EntityRepository<PaymentAppCollection>|null $appRepository
     * @param EntityRepository<PaymentRefundCollection>|null $refundRepository
     */
    private function service(
        EntityRepository $notificationRepository,
        GatewayInterface $gateway,
        ?EntityRepository $orderRepository = null,
        ?PaymentNotificationExtractorInterface $extractor = null,
        ?PaymentOrderService $orderService = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        ?EntityRepository $deliveryRepository = null,
        ?EntityRepository $appRepository = null,
        ?EntityRepository $refundRepository = null,
        ?StateMachineRegistry $refundStateMachine = null,
        bool $knownAttempt = true,
        ?PaymentAttemptEntity $matchedAttempt = null,
        ?PaymentConfigResolver $configResolver = null,
    ): PaymentNotificationProcessor {
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

        $orderService ??= $this->orderService($orderRepository ?? $this->orderRepository());
        $eventDispatcher ??= static::createStub(EventDispatcherInterface::class);

        $paidHandler = new PaymentOrderPaidHandler($orderService, PaymentEventDispatcherFixture::create($webhookService, $eventDispatcher));

        $refundService = new PaymentRefundService(
            $refundRepository ?? static::createStub(EntityRepository::class),
            $this->configResolver($gateway),
            $orderService,
            PaymentEventDispatcherFixture::create($webhookService, $eventDispatcher),
            new PaymentNotifyUrlBuilder(static::createStub(RequestStack::class), 'https://base.example.test'),
            new PaymentRefundStateHandler($refundStateMachine ?? static::createStub(StateMachineRegistry::class)),
            $this->initialStateIdLoader(),
            new MockClock(self::NOW),
            NumberRangeFixture::generator('PR1001'),
            $this->rowLock()
        );

        return new PaymentNotificationProcessor(
            $notificationRepository,
            $this->providerRegistry($extractor ?? $this->extractor(null)),
            $orderService,
            $refundService,
            $configResolver ?? $this->configResolver($gateway),
            $paidHandler,
            new StaticEntityRepository([new PaymentAttemptCollection($knownAttempt ? [$matchedAttempt ?? $this->paymentAttempt()] : [])]),
        );
    }

    private function providerRegistry(PaymentNotificationExtractorInterface $extractor): PaymentProviderRegistry
    {
        $channel = new PaymentProduct(
            technicalName: 'alipay_h5',
            providerTechnicalName: 'alipay',
            method: 'alipay',
            label: 'Alipay H5',
            interactions: [PaymentInteraction::REDIRECT],
        );

        $provider = new PaymentProvider(
            technicalName: 'alipay',
            label: 'Alipay',
            credentials: [],
            products: ['alipay_h5' => $channel],
            gatewayFactory: static::createStub(PaymentGatewayFactory::class),
            notificationExtractor: $extractor,
        );

        return new PaymentProviderRegistry([$provider]);
    }

    private function extractor(?string $paymentNo): PaymentNotificationExtractorInterface
    {
        $extractor = static::createStub(PaymentNotificationExtractorInterface::class);
        $extractor->method('extract')->willReturn(new PaymentNotificationData($paymentNo));

        return $extractor;
    }

    private function refundExtractor(string $refundNo): PaymentNotificationExtractorInterface
    {
        $extractor = static::createStub(PaymentNotificationExtractorInterface::class);
        $extractor->method('extract')->willReturn(new PaymentNotificationData(null, null, null, $refundNo));

        return $extractor;
    }

    private function gateway(NotificationResult $result): GatewayInterface
    {
        $gateway = static::createStub(GatewayInterface::class);
        $gateway->method('execute')->willReturnCallback(static function (object $request) use ($result): void {
            static::assertInstanceOf(Notify::class, $request);

            $model = $request->getModel();
            static::assertInstanceOf(PaymentGatewayModel::class, $model);

            $model->offsetSet(PaymentGatewayModel::RESULT, $result);
        });

        return $gateway;
    }

    private function configResolver(GatewayInterface $gateway): PaymentConfigResolver
    {
        $channel = new PaymentProduct(
            technicalName: 'alipay_h5',
            providerTechnicalName: 'alipay',
            method: 'alipay',
            label: 'Alipay H5',
            interactions: [PaymentInteraction::REDIRECT],
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
        );

        $resolved = new ResolvedPaymentConfig($provider, $channel, ['app_id' => 'original-app'], ['appid' => 'original-appid'], [], [], 'account-id');
        $resolver = static::createStub(PaymentConfigResolver::class);
        $resolver->method('resolveForAttempt')->willReturn($resolved);
        $resolver->method('resolveForOrder')->willReturn($resolved);

        return $resolver;
    }

    /**
     * @param EntityRepository<PaymentOrderCollection>|null $orderRepository
     */
    private function orderService(?EntityRepository $orderRepository = null, ?StateMachineRegistry $stateMachine = null): PaymentOrderService
    {
        return new PaymentOrderService(
            $orderRepository ?? $this->orderRepository(),
            $this->initialStateIdLoader(),
            new PaymentOrderStateHandler($stateMachine ?? static::createStub(StateMachineRegistry::class)),
            new MockClock(self::NOW),
            NumberRangeFixture::generator('PG1001'),
            $this->rowLock(),
            StaticEntityRepository::of(PaymentAttemptCollection::class),
        );
    }

    private function rowLock(): PaymentOrderRowLock
    {
        $rowLock = static::createStub(PaymentOrderRowLock::class);
        $rowLock->method('transaction')->willReturnCallback(static fn (\Closure $write) => $write());
        $rowLock->method('lockOrder')->willReturn(['paidAmount' => 1000, 'refundedAmount' => 0]);
        $rowLock->method('lockRefund')->willReturn(false);

        return $rowLock;
    }

    private function initialStateIdLoader(): InitialStateIdLoader
    {
        $loader = static::createStub(InitialStateIdLoader::class);
        $loader->method('get')->willReturn(self::CREATED_STATE);

        return $loader;
    }

    /**
     * @return EntityRepository<PaymentOrderCollection>
     */
    private function orderRepository(PaymentOrderEntity ...$orders): EntityRepository
    {
        $repository = static::createStub(EntityRepository::class);
        $repository->method('search')->willReturn($this->orderSearchResult(...$orders));

        return $repository;
    }

    /**
     * The notification is applied - the order is paid and the app is told - whichever way the
     * interface spelled its currency.
     */
    private function assertTheNotificationIsApplied(?string $currency): void
    {
        $notifyUrl = 'https://app.example.test/webhook';
        $order = $this->order(['notifyUrl' => $notifyUrl]);
        $paidOrder = $this->order(['paidAt' => new \DateTimeImmutable(self::NOW), 'paidAmount' => 1000, 'outPaymentNo' => 'channel-trade-1', 'stateId' => 'state-paid', 'notifyUrl' => $notifyUrl]);

        $notificationRepository = $this->notificationRepository(
            onUpdate: function (array $row): void {
                static::assertSame(PaymentNotificationEntity::RESULT_OK, $row['processResult']);
                static::assertTrue($row['processed']);
            }
        );

        $orderRepository = static::createMock(EntityRepository::class);
        $orderRepository->expects($this->exactly(2))
            ->method('search')
            ->willReturnOnConsecutiveCalls(
                $this->orderSearchResult($order),
                $this->orderSearchResult($paidOrder)
            );
        $orderRepository->expects($this->once())
            ->method('update')
            ->with(static::callback(static function (array $data): bool {
                static::assertSame(1000, $data[0]['paidAmount']);
                static::assertSame('channel-trade-1', $data[0]['outPaymentNo']);

                return true;
            }));

        $orderStateMachine = static::createMock(StateMachineRegistry::class);
        $orderStateMachine->expects($this->exactly(2))->method('transition');

        $eventDispatcher = static::createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(static::isInstanceOf(PaymentOrderPaidEvent::class));

        $delivery = new PaymentWebhookDeliveryEntity();
        $delivery->setUniqueIdentifier('delivery-id');
        $delivery->id = 'delivery-id';
        $delivery->paymentOrderId = 'order-id';
        $delivery->paymentAppId = 'app-id';
        $delivery->event = PaymentWebhookDispatcher::EVENT_ORDER_PAID;
        $delivery->url = $notifyUrl;

        $deliveryRepository = static::createMock(EntityRepository::class);
        $deliveryRepository->expects($this->once())
            ->method('create')
            ->with(static::callback(static fn (array $data): bool => $data[0]['event'] === PaymentWebhookDispatcher::EVENT_ORDER_PAID));
        $deliveryRepository->method('search')->willReturnOnConsecutiveCalls(
            $this->deliverySearchResult(),
            $this->deliverySearchResult($delivery)
        );

        $service = $this->service(
            $notificationRepository,
            $this->gateway(new NotificationResult(paymentNo: 'PG0001', signatureValid: true, succeeded: true, outPaymentNo: 'channel-trade-1', amount: 1000, currency: $currency, raw: ['trade' => 'raw'])),
            $orderRepository,
            $this->extractor('PG0001'),
            orderService: $this->orderService($orderRepository, $orderStateMachine),
            eventDispatcher: $eventDispatcher,
            deliveryRepository: $deliveryRepository,
            appRepository: $this->appRepository()
        );

        static::assertSame('notification-id', $service->process('alipay', 'raw-body', [], '1.2.3.4')->id);
    }

    /**
     * Doubles the collaborators a paid notification would write through, so that any write fails the
     * test - a notification that is not applied must not touch the order, the state machine, the
     * event dispatcher or the webhook queue.
     *
     * @return array{0: EntityRepository<PaymentOrderCollection>, 1: StateMachineRegistry, 2: EventDispatcherInterface, 3: EntityRepository<PaymentWebhookDeliveryCollection>}
     */
    private function expectNoPaymentApplied(PaymentOrderEntity $order): array
    {
        $orderRepository = static::createMock(EntityRepository::class);
        $orderRepository->method('search')->willReturn($this->orderSearchResult($order));
        $orderRepository->expects($this->never())->method('update');

        $orderStateMachine = static::createMock(StateMachineRegistry::class);
        $orderStateMachine->expects($this->never())->method('transition');

        $eventDispatcher = static::createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->never())->method('dispatch');

        $deliveryRepository = static::createMock(EntityRepository::class);
        $deliveryRepository->expects($this->never())->method('create');

        return [$orderRepository, $orderStateMachine, $eventDispatcher, $deliveryRepository];
    }

    /**
     * @return EntitySearchResult<PaymentOrderCollection>
     */
    private function orderSearchResult(PaymentOrderEntity ...$orders): EntitySearchResult
    {
        return new EntitySearchResult(
            PaymentOrderEntity::ENTITY_NAME,
            \count($orders),
            new PaymentOrderCollection($orders),
            null,
            new Criteria(),
            Context::createDefaultContext()
        );
    }

    /**
     * @return EntityRepository<PaymentNotificationCollection>
     */
    private function notificationRepository(?\Closure $onCreate = null, ?\Closure $onUpdate = null): EntityRepository
    {
        $notification = $this->notificationEntity();

        $repository = static::createMock(EntityRepository::class);

        $create = $repository->expects($this->once())->method('create');
        if ($onCreate !== null) {
            $create->with(static::callback(static function (array $data) use ($onCreate): bool {
                $onCreate($data[0]);

                return true;
            }));
        }

        $update = $repository->expects($this->once())->method('update');
        if ($onUpdate !== null) {
            $update->with(static::callback(static function (array $data) use ($onUpdate): bool {
                $onUpdate($data[0]);

                return true;
            }));
        }

        $repository->method('search')->willReturn(new EntitySearchResult(
            'payment_notification',
            1,
            new PaymentNotificationCollection([$notification]),
            null,
            new Criteria(),
            Context::createDefaultContext()
        ));

        return $repository;
    }

    /**
     * A delivery repository that already holds the delivery of the announced event, so that
     * announcing again is a no-op.
     *
     * @return EntityRepository<PaymentWebhookDeliveryCollection>
     */
    private function deliveryRepositoryWithExistingDelivery(): EntityRepository
    {
        $delivery = $this->queuedDelivery();

        $repository = static::createMock(EntityRepository::class);
        $repository->expects($this->never())->method('create');
        $repository->expects($this->never())->method('update');
        $repository->method('search')->willReturn($this->deliverySearchResult($delivery));

        return $repository;
    }

    /**
     * @return EntitySearchResult<PaymentWebhookDeliveryCollection>
     */
    private function deliverySearchResult(PaymentWebhookDeliveryEntity ...$deliveries): EntitySearchResult
    {
        return new EntitySearchResult(
            'payment_webhook_delivery',
            \count($deliveries),
            new PaymentWebhookDeliveryCollection($deliveries),
            null,
            new Criteria(),
            Context::createDefaultContext()
        );
    }

    private function queuedDelivery(): PaymentWebhookDeliveryEntity
    {
        $delivery = new PaymentWebhookDeliveryEntity();
        $delivery->setUniqueIdentifier('delivery-id');
        $delivery->id = 'delivery-id';
        $delivery->paymentOrderId = 'order-id';
        $delivery->paymentAppId = 'app-id';
        $delivery->event = PaymentWebhookDispatcher::EVENT_ORDER_PAID;
        $delivery->url = 'https://app.example.test/webhook';

        return $delivery;
    }

    /**
     * @return EntityRepository<PaymentAppCollection>
     */
    private function appRepository(): EntityRepository
    {
        $repository = static::createStub(EntityRepository::class);
        $repository->method('search')->willReturn(new EntitySearchResult(
            'payment_app',
            1,
            new PaymentAppCollection([$this->app()]),
            null,
            new Criteria(),
            Context::createDefaultContext()
        ));

        return $repository;
    }

    private function notificationEntity(): PaymentNotificationEntity
    {
        $notification = new PaymentNotificationEntity();
        $notification->setUniqueIdentifier('notification-id');
        $notification->id = 'notification-id';
        $notification->providerTechnicalName = 'alipay';

        return $notification;
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
        $order->activeAttemptId = 'attempt-id';
        $order->activeAttempt = $this->paymentAttempt();
        $order->amount = 1000;
        $order->currency = 'CNY';
        $order->subject = 'An order';
        $order->stateId = self::CREATED_STATE;
        $order->paymentApp = $this->app();

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

        if (\array_key_exists('outPaymentNo', $overrides)) {
            $order->outPaymentNo = (string) $overrides['outPaymentNo'];
        }

        if (\array_key_exists('notifyUrl', $overrides)) {
            $order->notifyUrl = (string) $overrides['notifyUrl'];
        }

        return $order;
    }

    /**
     * A refund of the order above that is waiting for the asynchronous confirmation of the channel.
     */
    private function refund(): PaymentRefundEntity
    {
        $refund = new PaymentRefundEntity();
        $refund->setUniqueIdentifier('refund-id');
        $refund->id = 'refund-id';
        $refund->refundNo = 'PR0001';
        $refund->paymentOrderId = 'order-id';
        $refund->paymentAttemptId = 'attempt-id';
        $refund->outRefundNo = 'app-refund-1';
        $refund->amount = 400;
        $refund->currency = 'CNY';
        $refund->stateId = PaymentRefundStates::STATE_PROCESSING;

        return $refund;
    }

    private function paymentAttempt(): PaymentAttemptEntity
    {
        $attempt = new PaymentAttemptEntity();
        $attempt->id = 'attempt-id';
        $attempt->setUniqueIdentifier('attempt-id');
        $attempt->paymentOrderId = 'order-id';
        $attempt->attemptNo = 'PG0001';
        $attempt->accountId = 'account-id';
        $attempt->providerTechnicalName = 'alipay';
        $attempt->channelTechnicalName = 'alipay_h5';
        $attempt->method = 'alipay';
        $attempt->interaction = PaymentInteraction::REDIRECT;
        $attempt->status = PaymentAttemptEntity::STATUS_HANDOVER;

        return $attempt;
    }

    private function app(): PaymentAppEntity
    {
        $app = new PaymentAppEntity();
        $app->setUniqueIdentifier('app-id');
        $app->id = 'app-id';
        $app->technicalName = 'demo-app';
        $app->type = PaymentAppEntity::TYPE_EXTERNAL;
        // the app secret is stored encrypted, the webhook signer decrypts it to sign the payload
        $app->appSecret = (new PaymentCredentialCipher('test-secret'))->encryptValue('secret');
        $app->active = true;

        return $app;
    }
}
