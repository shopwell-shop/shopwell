<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Order;

use Doctrine\DBAL\Connection;
use Payum\Core\GatewayInterface;
use Payum\Core\Request\Generic;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\App\PaymentCredentialCipher;
use Shopwell\Core\System\Payment\App\PaymentSignatureService;
use Shopwell\Core\System\Payment\Exception\PaymentGatewayException;
use Shopwell\Core\System\Payment\Exception\PaymentOrderException;
use Shopwell\Core\System\Payment\Gateway\PaymentChannelCloser;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayFactory;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\Gateway\Request\CloseResult;
use Shopwell\Core\System\Payment\Gateway\Request\PaymentClose;
use Shopwell\Core\System\Payment\Gateway\Request\PaymentQuery;
use Shopwell\Core\System\Payment\Gateway\Request\QueryResult;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationExtractorInterface;
use Shopwell\Core\System\Payment\Order\Aggregate\PaymentAttempt\PaymentAttemptCollection;
use Shopwell\Core\System\Payment\Order\Aggregate\PaymentAttempt\PaymentAttemptEntity;
use Shopwell\Core\System\Payment\Order\PaymentOrderCloser;
use Shopwell\Core\System\Payment\Order\PaymentOrderCollection;
use Shopwell\Core\System\Payment\Order\PaymentOrderEntity;
use Shopwell\Core\System\Payment\Order\PaymentOrderPaidHandler;
use Shopwell\Core\System\Payment\Order\PaymentOrderQueryResultHandler;
use Shopwell\Core\System\Payment\Order\PaymentOrderReconciler;
use Shopwell\Core\System\Payment\Order\PaymentOrderService;
use Shopwell\Core\System\Payment\Order\PaymentOrderStateHandler;
use Shopwell\Core\System\Payment\Order\PaymentOrderStates;
use Shopwell\Core\System\Payment\Product\PaymentInteraction;
use Shopwell\Core\System\Payment\Product\PaymentProduct;
use Shopwell\Core\System\Payment\Provider\Capability\PaymentCapability;
use Shopwell\Core\System\Payment\Provider\Config\PaymentConfigResolver;
use Shopwell\Core\System\Payment\Provider\PaymentProvider;
use Shopwell\Core\System\Payment\Provider\ResolvedPaymentConfig;
use Shopwell\Core\System\Payment\Webhook\PaymentWebhookDispatcher;
use Shopwell\Core\System\Payment\Webhook\PaymentWebhookSigner;
use Shopwell\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Shopwell\Core\System\StateMachine\StateMachineRegistry;
use Shopwell\Core\System\StateMachine\Transition;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopwell\Tests\Unit\Core\System\Payment\NumberRangeFixture;
use Shopwell\Tests\Unit\Core\System\Payment\Order\_fixtures\PaymentOrderRowLockStub;
use Shopwell\Tests\Unit\Core\System\Payment\PaymentEventDispatcherFixture;
use Symfony\Component\Clock\MockClock;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentOrderCloser::class)]
class PaymentOrderCloserTest extends TestCase
{
    private const NOW = '2024-01-01T00:00:00+00:00';

    /**
     * What the fake channel was asked, in order.
     *
     * @var list<class-string>
     */
    private array $asked = [];

    public function testAClosedTradeClosesTheOrderLocally(): void
    {
        $orderRepository = static::createMock(EntityRepository::class);
        $orderRepository->expects($this->once())
            ->method('update')
            ->with(static::callback(function (array $data): bool {
                /** @var array{id: string, closedAt: \DateTimeInterface} $row */
                $row = $data[0];

                static::assertSame('order-id', $row['id']);
                static::assertSame('2024-01-01 00:00:00', $row['closedAt']->format('Y-m-d H:i:s'));

                return true;
            }));

        $stateMachine = static::createMock(StateMachineRegistry::class);
        $stateMachine->expects($this->once())
            ->method('transition')
            ->with(static::callback(static fn (Transition $t): bool => $t->getTransitionName() === PaymentOrderStates::ACTION_CLOSE));

        $result = $this->closer($orderRepository, $this->gateway(CloseResult::STATUS_CLOSED), $stateMachine)
            ->close($this->order());

        static::assertSame(CloseResult::STATUS_CLOSED, $result->status);
    }

    public function testATradeTheChannelDoesNotKnowIsClosedLocallyAsWell(): void
    {
        $orderRepository = static::createMock(EntityRepository::class);
        $orderRepository->expects($this->once())->method('update');

        $stateMachine = static::createMock(StateMachineRegistry::class);
        $stateMachine->expects($this->once())->method('transition');

        $result = $this->closer($orderRepository, $this->gateway(CloseResult::STATUS_NOT_FOUND), $stateMachine)
            ->close($this->order());

        static::assertSame(CloseResult::STATUS_NOT_FOUND, $result->status);
    }

    /**
     * The close turned up a payment: booking it is the correct outcome, so it happens before the
     * caller is told that the order was not closed - an app must never believe a paid order is off.
     */
    public function testATradeThatIsPaidIsBookedAndTheCloseIsRefused(): void
    {
        $orderRepository = static::createMock(EntityRepository::class);
        $orderRepository->expects($this->never())->method('update');

        $stateMachine = static::createMock(StateMachineRegistry::class);
        $stateMachine->expects($this->never())->method('transition');

        $gateway = $this->gateway(CloseResult::STATUS_ALREADY_PAID);

        $this->expectExceptionObject(PaymentOrderException::orderAlreadyPaid('PG0001'));

        try {
            $this->closer($orderRepository, $gateway, $stateMachine)->close($this->order());
        } finally {
            // the reconciliation asked the channel about the payment exactly once - and, because the
            // channel answered "pending", it wrote nothing
            static::assertSame([PaymentClose::class, PaymentQuery::class], $this->asked);
        }
    }

    public function testAGatewayFailureLeavesTheOrderOpenAndSurfaces(): void
    {
        $orderRepository = static::createMock(EntityRepository::class);
        $orderRepository->expects($this->never())->method('update');

        $stateMachine = static::createMock(StateMachineRegistry::class);
        $stateMachine->expects($this->never())->method('transition');

        $failure = PaymentGatewayException::gatewayError('alipay_h5', 'the channel is down');

        $this->expectExceptionObject($failure);

        $this->closer($orderRepository, $this->failingGateway($failure), $stateMachine)->close($this->order());
    }

    public function testAPaidOrderIsRefusedWithoutTouchingTheChannel(): void
    {
        $orderRepository = static::createMock(EntityRepository::class);
        $orderRepository->expects($this->never())->method('update');

        $stateMachine = static::createMock(StateMachineRegistry::class);
        $stateMachine->expects($this->never())->method('transition');

        $gateway = $this->failingGateway(new \RuntimeException('the channel must not be asked'));

        $this->expectExceptionObject(PaymentOrderException::orderAlreadyPaid('PG0001'));

        $this->closer($orderRepository, $gateway, $stateMachine)
            ->close($this->order(['paidAt' => new \DateTimeImmutable(self::NOW)]));
    }

    public function testAClosedOrderIsRefusedWithoutTouchingTheChannel(): void
    {
        $orderRepository = static::createMock(EntityRepository::class);
        $orderRepository->expects($this->never())->method('update');

        $stateMachine = static::createMock(StateMachineRegistry::class);
        $stateMachine->expects($this->never())->method('transition');

        $gateway = $this->failingGateway(new \RuntimeException('the channel must not be asked'));

        $this->expectExceptionObject(PaymentOrderException::orderAlreadyClosed('PG0001'));

        $this->closer($orderRepository, $gateway, $stateMachine)
            ->close($this->order(['closedAt' => new \DateTimeImmutable(self::NOW)]));
    }

    /**
     * The gateway of the test channel: a close is answered with the given status, a query - which the
     * reconciliation runs after a paid answer - with "pending", so nothing is written and the test can
     * count the calls.
     */
    private function gateway(string $closeStatus): GatewayInterface
    {
        $gateway = static::createStub(GatewayInterface::class);
        $gateway->method('execute')->willReturnCallback(function (Generic $request) use ($closeStatus): void {
            $this->asked[] = $request::class;

            $model = $request->getModel();
            \assert($model instanceof PaymentGatewayModel);

            if ($request instanceof PaymentClose) {
                $model->recordResponse(['status' => $closeStatus]);
                $model->offsetSet(PaymentGatewayModel::RESULT, new CloseResult($closeStatus, $closeStatus));

                return;
            }

            $model->offsetSet(PaymentGatewayModel::RESULT, new QueryResult(QueryResult::STATUS_PENDING, 'channel-trade-1', 1000, 'CNY'));
        });

        return $gateway;
    }

    private function failingGateway(\Throwable $failure): GatewayInterface
    {
        $gateway = static::createStub(GatewayInterface::class);
        $gateway->method('execute')->willThrowException($failure);

        return $gateway;
    }

    /**
     * @param EntityRepository<PaymentOrderCollection> $orderRepository
     */
    private function closer(EntityRepository $orderRepository, GatewayInterface $gateway, StateMachineRegistry $stateMachine): PaymentOrderCloser
    {
        $configResolver = $this->configResolver($gateway);
        $clock = new MockClock(self::NOW);

        $initialStateIdLoader = static::createStub(InitialStateIdLoader::class);
        $initialStateIdLoader->method('get')->willReturn('state-created');

        $orderService = new PaymentOrderService(
            $orderRepository,
            $initialStateIdLoader,
            new PaymentOrderStateHandler($stateMachine),
            $clock,
            NumberRangeFixture::generator('PG1001'),
            new PaymentOrderRowLockStub(),
            StaticEntityRepository::of(PaymentAttemptCollection::class),
        );

        $webhooks = new PaymentWebhookDispatcher(
            static::createStub(EntityRepository::class),
            static::createStub(EntityRepository::class),
            new PaymentWebhookSigner(new PaymentSignatureService(), new PaymentCredentialCipher('test-secret'), $clock),
            static::createStub(HttpClientInterface::class),
            $clock,
            static::createStub(LoggerInterface::class),
            static::createStub(Connection::class),
        );

        $paidHandler = new PaymentOrderPaidHandler($orderService, PaymentEventDispatcherFixture::create($webhooks));

        return new PaymentOrderCloser(
            new PaymentChannelCloser(static::createStub(EntityRepository::class), $configResolver, NumberRangeFixture::generator('PX1001')),
            $orderService,
            new PaymentOrderReconciler(
                $orderRepository,
                static::createStub(EntityRepository::class),
                $configResolver,
                $orderService,
                $paidHandler,
                new PaymentOrderQueryResultHandler($orderService, $webhooks, $paidHandler, $clock),
                $clock,
                static::createStub(LoggerInterface::class),
            ),
        );
    }

    private function configResolver(GatewayInterface $gateway): PaymentConfigResolver
    {
        $channel = new PaymentProduct(
            technicalName: 'alipay_h5',
            providerTechnicalName: 'alipay',
            method: 'alipay',
            label: 'Alipay H5',
            interactions: [PaymentInteraction::REDIRECT],
            capabilities: [PaymentCapability::CAPTURE, PaymentCapability::CLOSE, PaymentCapability::QUERY],
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
            // alipay closes and queries; the reconciler of the closer needs the query as well
            capabilities: [PaymentCapability::CAPTURE, PaymentCapability::CLOSE, PaymentCapability::QUERY],
        );

        $resolved = new ResolvedPaymentConfig($provider, $channel, [], [], [], [], 'account-id');
        $resolver = static::createStub(PaymentConfigResolver::class);
        $resolver->method('resolveForOrder')->willReturn($resolved);
        $resolver->method('resolveForAttempt')->willReturn($resolved);

        return $resolver;
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
        $order->stateId = 'state-opened';
        $order->returnUrl = 'https://return.example.test';
        $order->paymentApp = $this->app();

        foreach ($overrides as $property => $value) {
            // @phpstan-ignore property.dynamicName (the overrides name the properties of the entity on purpose)
            $order->$property = $value;
        }

        return $order;
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
}
