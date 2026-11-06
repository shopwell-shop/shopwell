<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Order;

use Payum\Core\GatewayInterface;
use Payum\Core\Request\Capture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\Exception\PaymentGatewayException;
use Shopwell\Core\System\Payment\Exception\PaymentOrderException;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayFactory;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\Gateway\Struct\PaymentHandover;
use Shopwell\Core\System\Payment\Gateway\Struct\PaymentStartResult;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationExtractorInterface;
use Shopwell\Core\System\Payment\Notification\PaymentNotifyUrlBuilder;
use Shopwell\Core\System\Payment\Order\Aggregate\PaymentAttempt\PaymentAttemptCollection;
use Shopwell\Core\System\Payment\Order\Aggregate\PaymentAttempt\PaymentAttemptEntity;
use Shopwell\Core\System\Payment\Order\Aggregate\PaymentTransaction\PaymentTransactionCollection;
use Shopwell\Core\System\Payment\Order\PaymentOrderCollection;
use Shopwell\Core\System\Payment\Order\PaymentOrderEntity;
use Shopwell\Core\System\Payment\Order\PaymentOrderService;
use Shopwell\Core\System\Payment\Order\PaymentOrderStarter;
use Shopwell\Core\System\Payment\Order\PaymentOrderStateHandler;
use Shopwell\Core\System\Payment\Product\PaymentInteraction;
use Shopwell\Core\System\Payment\Product\PaymentProduct;
use Shopwell\Core\System\Payment\Product\PaymentScene;
use Shopwell\Core\System\Payment\Provider\Config\PaymentConfigField;
use Shopwell\Core\System\Payment\Provider\PaymentProvider;
use Shopwell\Core\System\Payment\Provider\ResolvedPaymentConfig;
use Shopwell\Core\System\Payment\Routing\PaymentContext;
use Shopwell\Core\System\Payment\Routing\PaymentRoute;
use Shopwell\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Shopwell\Core\System\StateMachine\StateMachineRegistry;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopwell\Tests\Unit\Core\System\Payment\Account\_fixtures\PaymentAccountLockStub;
use Shopwell\Tests\Unit\Core\System\Payment\NumberRangeFixture;
use Shopwell\Tests\Unit\Core\System\Payment\Order\_fixtures\PaymentOrderRowLockStub;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentOrderStarter::class)]
class PaymentOrderStarterTest extends TestCase
{
    private PaymentOrderEntity $order;

    private PaymentAppEntity $app;

    /**
     * @var StaticEntityRepository<PaymentAttemptCollection>
     */
    private StaticEntityRepository $attempts;

    /**
     * @var StaticEntityRepository<PaymentTransactionCollection>
     */
    private StaticEntityRepository $transactions;

    /**
     * @var StaticEntityRepository<PaymentOrderCollection>
     */
    private StaticEntityRepository $orders;

    private PaymentOrderStarter $starter;

    protected function setUp(): void
    {
        $this->order = new PaymentOrderEntity();
        $this->order->id = 'order-id';
        $this->order->setUniqueIdentifier('order-id');
        $this->order->paymentNo = 'PG0001';
        $this->order->outTradeNo = 'business-trade';
        $this->order->paymentAppId = 'app-id';
        $this->order->amount = 1000;
        $this->order->currency = 'CNY';
        $this->order->subject = 'Fixed business obligation';
        $this->order->stateId = 'opened';
        $this->app = new PaymentAppEntity();
        $this->app->id = 'app-id';
        $this->app->technicalName = 'demo-app';

        $this->attempts = StaticEntityRepository::of(PaymentAttemptCollection::class);
        $this->attempts->searches = array_fill(0, 20, fn (): PaymentAttemptCollection => new PaymentAttemptCollection([$this->persistedAttempt()]));
        $this->transactions = StaticEntityRepository::of(PaymentTransactionCollection::class);
        $this->orders = StaticEntityRepository::of(PaymentOrderCollection::class);
        $this->orders->searches = array_fill(0, 20, function (): PaymentOrderCollection {
            if ($this->attempts->creates !== []) {
                $this->order->activeAttempt = $this->persistedAttempt();
                $this->order->activeAttemptId = $this->order->activeAttempt->id;
            }

            return new PaymentOrderCollection([$this->order]);
        });
        $initial = static::createStub(InitialStateIdLoader::class);
        $initial->method('get')->willReturn('created');
        $orderService = new PaymentOrderService(
            $this->orders,
            $initial,
            new PaymentOrderStateHandler(static::createStub(StateMachineRegistry::class)),
            new MockClock('2026-11-05T12:00:00+00:00'),
            NumberRangeFixture::generator('PG1001'),
            new PaymentOrderRowLockStub(),
            $this->attempts,
        );
        $this->starter = new PaymentOrderStarter(
            $this->transactions,
            $this->attempts,
            $this->orders,
            $orderService,
            new PaymentNotifyUrlBuilder(new RequestStack(), 'https://shop.example.test'),
            NumberRangeFixture::generator('PX1001'),
            new PaymentOrderRowLockStub(),
            new PaymentAccountLockStub(),
        );
    }

    public function testClaimFreezesTheRouteAndExternalIdentityBeforeSendingTheRequest(): void
    {
        $gateway = static::createMock(GatewayInterface::class);
        $gateway->expects($this->once())->method('execute')->willReturnCallback(function (Capture $request): void {
            $attempt = $this->persistedAttempt();
            static::assertSame(PaymentAttemptEntity::STATUS_PENDING, $attempt->status);
            static::assertSame('merchant-account', $attempt->accountId);
            static::assertSame('alipay_h5', $attempt->channelTechnicalName);
            static::assertSame('browser', $attempt->context['scene']);
            static::assertSame(['merchant-account' => ['alipay_h5' => ['openid' => 'scoped-payer']]], $attempt->context['account_runtime_params']);
            static::assertSame(['appid' => 'frozen-app'], $attempt->configuration['params']);
            static::assertArrayNotHasKey('credentials', $attempt->configuration);
            static::assertSame($attempt->id, $this->orders->updates[0][0]['activeAttemptId']);
            static::assertSame($attempt->id, $this->transactions->creates[0][0]['paymentAttemptId']);
            $model = $request->getModel();
            static::assertInstanceOf(PaymentGatewayModel::class, $model);
            static::assertSame($attempt->attemptNo, $model->offsetGet(PaymentGatewayModel::PAYMENT_NO));
            static::assertNotSame($this->order->paymentNo, $model->offsetGet(PaymentGatewayModel::PAYMENT_NO));
            $model->offsetSet(PaymentGatewayModel::RESULT, $this->handover());
        });

        $result = $this->starter->start($this->app, $this->order, $this->route($gateway));

        static::assertSame('https://provider.example.test/pay', $result->params->payUrl);
        static::assertSame(PaymentAttemptEntity::STATUS_HANDOVER, $this->persistedAttempt()->status);
        static::assertSame('external-trade', $this->persistedAttempt()->outPaymentNo);
    }

    public function testUnknownOutcomeBlocksNewRequestsEvenWhenTheCallerResolvesAnotherAccount(): void
    {
        $gateway = static::createMock(GatewayInterface::class);
        $gateway->expects($this->once())->method('execute')->willThrowException(new \RuntimeException('timed out'));
        try {
            $this->starter->start($this->app, $this->order, $this->route($gateway));
            static::fail('The uncertain provider outcome must be reported.');
        } catch (PaymentGatewayException) {
            static::assertSame(PaymentAttemptEntity::STATUS_UNKNOWN, $this->persistedAttempt()->status);
        }
        $otherGateway = static::createMock(GatewayInterface::class);
        $otherGateway->expects($this->never())->method('execute');

        $this->expectExceptionObject(PaymentOrderException::startPending('PG0001'));
        $this->starter->start($this->app, $this->order, $this->route($otherGateway, accountId: 'new-account'));
    }

    public function testSuccessfulHandoverIsReplayedAfterRouteConfigurationChanges(): void
    {
        $gateway = static::createMock(GatewayInterface::class);
        $gateway->expects($this->once())->method('execute')->willReturnCallback(function (Capture $request): void {
            $request->getModel()[PaymentGatewayModel::RESULT] = $this->handover();
        });
        $this->starter->start($this->app, $this->order, $this->route($gateway));
        $other = static::createMock(GatewayInterface::class);
        $other->expects($this->never())->method('execute');

        $result = $this->starter->start($this->app, $this->order, $this->route($other, accountId: 'new-account'));

        static::assertSame('https://provider.example.test/pay', $result->params->payUrl);
        static::assertCount(1, $this->attempts->creates);
        static::assertSame('merchant-account', $this->persistedAttempt()->accountId);
    }

    public function testAnotherCallerDuringTheProviderRequestSeesTheClaimAndCannotSubmit(): void
    {
        $gateway = static::createMock(GatewayInterface::class);
        $gateway->expects($this->once())->method('execute')->willReturnCallback(function (Capture $request): void {
            $other = static::createMock(GatewayInterface::class);
            $other->expects($this->never())->method('execute');
            try {
                $this->starter->start($this->app, $this->order, $this->route($other));
                static::fail('The concurrent caller must see the pending attempt.');
            } catch (PaymentOrderException $exception) {
                static::assertSame(PaymentOrderException::startPending('PG0001')->getErrorCode(), $exception->getErrorCode());
            }
            $request->getModel()[PaymentGatewayModel::RESULT] = $this->handover();
        });

        $this->starter->start($this->app, $this->order, $this->route($gateway));

        static::assertCount(1, $this->transactions->creates);
    }

    public function testFastPaidCallbackCannotBeDowngradedByTheReturningCapture(): void
    {
        $gateway = static::createMock(GatewayInterface::class);
        $gateway->expects($this->once())->method('execute')->willReturnCallback(function (Capture $request): void {
            $this->attempts->updates[] = [['id' => $this->persistedAttempt()->id, 'status' => PaymentAttemptEntity::STATUS_PAID, 'outPaymentNo' => 'settled-trade']];
            $request->getModel()[PaymentGatewayModel::RESULT] = $this->handover();
        });

        $this->starter->start($this->app, $this->order, $this->route($gateway));

        static::assertSame(PaymentAttemptEntity::STATUS_PAID, $this->persistedAttempt()->status);
        static::assertSame('settled-trade', $this->persistedAttempt()->outPaymentNo);
    }

    public function testProviderCannotReplaceTheClaimedInteractionWithAnotherHandover(): void
    {
        $gateway = static::createMock(GatewayInterface::class);
        $gateway->expects($this->once())->method('execute')->willReturnCallback(static function (Capture $request): void {
            $request->getModel()[PaymentGatewayModel::RESULT] = new PaymentStartResult(new PaymentHandover(PaymentInteraction::QRCODE, 'weixin://pay'));
        });

        try {
            $this->starter->start($this->app, $this->order, $this->route($gateway));
            static::fail('The provider must honor the selected interaction.');
        } catch (PaymentGatewayException) {
            static::assertSame(PaymentAttemptEntity::STATUS_UNKNOWN, $this->persistedAttempt()->status);
            static::assertNull($this->persistedAttempt()->handover);
        }
    }

    public function testSettledObligationCannotCreateAnAttempt(): void
    {
        $this->order->paidAt = new \DateTimeImmutable();
        $gateway = static::createMock(GatewayInterface::class);
        $gateway->expects($this->never())->method('execute');

        $this->expectExceptionObject(PaymentOrderException::orderNotPayable('PG0001'));
        $this->starter->start($this->app, $this->order, $this->route($gateway));
    }

    private function persistedAttempt(): PaymentAttemptEntity
    {
        $payload = $this->attempts->creates[0][0];
        foreach ($this->attempts->updates as $update) {
            $payload = array_replace($payload, $update[0]);
        }
        $attempt = new PaymentAttemptEntity();
        $attempt->assign($payload);
        $attempt->setUniqueIdentifier($attempt->id);

        return $attempt;
    }

    private function handover(): PaymentStartResult
    {
        return new PaymentStartResult(new PaymentHandover(PaymentInteraction::REDIRECT, 'https://provider.example.test/pay'), 'external-trade');
    }

    private function route(GatewayInterface $gateway, string $accountId = 'merchant-account'): PaymentRoute
    {
        $factory = static::createStub(PaymentGatewayFactory::class);
        $factory->method('createForPayment')->willReturn($gateway);
        $product = new PaymentProduct(technicalName: 'alipay_h5', providerTechnicalName: 'alipay', label: 'Alipay H5', interactions: [PaymentInteraction::REDIRECT], method: 'alipay', params: [new PaymentConfigField(name: 'token', secret: true)]);
        $provider = new PaymentProvider(
            technicalName: 'alipay',
            label: 'Alipay',
            credentials: [],
            products: ['alipay_h5' => $product],
            gatewayFactory: $factory,
            notificationExtractor: static::createStub(PaymentNotificationExtractorInterface::class),
        );

        return new PaymentRoute(
            new ResolvedPaymentConfig($provider, $product, ['secret' => 'never persist'], ['appid' => 'frozen-app', 'token' => 'never snapshot this'], [], [], $accountId),
            new PaymentContext('CNY', new PaymentScene('browser', 'web'), accountRuntimeParams: [$accountId => ['alipay_h5' => ['openid' => 'scoped-payer']]]),
            PaymentInteraction::REDIRECT,
        );
    }
}
