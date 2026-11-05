<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Cashier;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\Cashier\PaymentCashierConfigCollection;
use Shopwell\Core\System\Payment\Cashier\PaymentCashierConfigEntity;
use Shopwell\Core\System\Payment\Cashier\PaymentCashierException;
use Shopwell\Core\System\Payment\Cashier\PaymentCashierService;
use Shopwell\Core\System\Payment\Cashier\PaymentCashierSessionCollection;
use Shopwell\Core\System\Payment\Cashier\PaymentCashierSessionEntity;
use Shopwell\Core\System\Payment\Cashier\PaymentCashierSessionLock;
use Shopwell\Core\System\Payment\Exception\PaymentOrderException;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayFactory;
use Shopwell\Core\System\Payment\Gateway\Struct\PaymentHandover;
use Shopwell\Core\System\Payment\Gateway\Struct\PaymentStartResult;
use Shopwell\Core\System\Payment\Method\PaymentMethod;
use Shopwell\Core\System\Payment\Method\PaymentMethodRegistry;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationExtractorInterface;
use Shopwell\Core\System\Payment\Order\PaymentOrderEntity;
use Shopwell\Core\System\Payment\Order\PaymentOrderPresenter;
use Shopwell\Core\System\Payment\Order\PaymentOrderService;
use Shopwell\Core\System\Payment\Order\PaymentOrderStarter;
use Shopwell\Core\System\Payment\Order\Struct\PaymentOrderRequest;
use Shopwell\Core\System\Payment\Product\PaymentInteraction;
use Shopwell\Core\System\Payment\Product\PaymentProduct;
use Shopwell\Core\System\Payment\Product\PaymentScene;
use Shopwell\Core\System\Payment\Provider\PaymentProvider;
use Shopwell\Core\System\Payment\Provider\ResolvedPaymentConfig;
use Shopwell\Core\System\Payment\Routing\PaymentContext;
use Shopwell\Core\System\Payment\Routing\PaymentRoute;
use Shopwell\Core\System\Payment\Routing\PaymentRouteResolver;
use Shopwell\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateCollection;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentCashierService::class)]
class PaymentCashierServiceTest extends TestCase
{
    /**
     * @var StaticEntityRepository<PaymentCashierConfigCollection>
     */
    private StaticEntityRepository $configs;

    /**
     * @var StaticEntityRepository<PaymentCashierSessionCollection>
     */
    private StaticEntityRepository $sessions;

    private PaymentRouteResolver&MockObject $routes;

    private PaymentOrderService&MockObject $orders;

    private PaymentOrderStarter&MockObject $starter;

    private PaymentCashierService $cashier;

    private bool $locked = false;

    protected function setUp(): void
    {
        $config = new PaymentCashierConfigEntity();
        $config->id = 'config';
        $config->setUniqueIdentifier($config->id);
        $config->allowedMethods = ['card'];
        $this->configs = new StaticEntityRepository([new PaymentCashierConfigCollection([$config])]);
        $this->sessions = StaticEntityRepository::of(PaymentCashierSessionCollection::class);
        $this->routes = $this->createMock(PaymentRouteResolver::class);
        $this->orders = $this->createMock(PaymentOrderService::class);
        $this->starter = $this->createMock(PaymentOrderStarter::class);
        $lock = static::createStub(PaymentCashierSessionLock::class);
        $lock->method('transaction')->willReturnCallback(function (string $id, \Closure $write): mixed {
            $this->locked = true;
            try {
                return $write();
            } finally {
                $this->locked = false;
            }
        });
        $this->cashier = new PaymentCashierService(
            $this->configs,
            $this->sessions,
            $this->routes,
            new PaymentMethodRegistry([new PaymentMethod('card', 'Card')]),
            $this->orders,
            $this->starter,
            new PaymentOrderPresenter(StaticEntityRepository::of(StateMachineStateCollection::class, [new StateMachineStateCollection()])),
            new MockClock('2026-01-01T00:00:00Z'),
            $lock,
        );
    }

    public function testCreateFreezesBusinessContextAndProfileWithoutOpeningAnOrder(): void
    {
        $this->routes->expects($this->never())->method('resolveMethod');
        $this->orders->expects($this->never())->method('create');
        $this->starter->expects($this->never())->method('start');
        $this->sessions->searches = [new PaymentCashierSessionCollection(), function (): PaymentCashierSessionCollection {
            $row = $this->sessions->creates[0][0];
            $session = (new PaymentCashierSessionEntity())->assign($row);
            $session->setUniqueIdentifier($session->id);

            return new PaymentCashierSessionCollection([$session]);
        }];
        $context = new PaymentContext('CNY', new PaymentScene('mini_program', 'mini', 'wechat'), [PaymentInteraction::SDK], ['plugin_mini'], accountRuntimeParams: ['account' => ['plugin_mini' => ['openid' => 'trusted-payer']]]);

        $session = $this->cashier->create($this->app(), 'mini', ['out_trade_no' => 'purchase', 'amount' => 1999, 'subject' => 'Purchase', 'attach' => 'immutable'], $context);

        static::assertSame('immutable', $session->payload['attach']);
        static::assertSame('CNY', $session->payload['currency']);
        static::assertSame(['card'], $session->allowedMethods);
        static::assertSame('mini_program', $session->paymentContext['scene']['name']);
        static::assertSame(['account' => ['plugin_mini' => ['openid' => 'trusted-payer']]], $session->paymentContext['account_runtime_params']);
        static::assertSame('2026-01-01T00:30:00+00:00', $session->expireAt->format(\DATE_ATOM));
        static::assertNull($session->paymentOrderId);
    }

    public function testIdempotentCreateReusesFrozenProfileInsteadOfApplyingNewSettings(): void
    {
        $this->starter->expects($this->never())->method('start');
        $this->routes->expects($this->never())->method('resolveMethod');
        $session = $this->session();
        $session->payload += ['body' => null, 'notify_url' => null, 'return_url' => null, 'client_ip' => null];
        // The database may reorder JSON object keys.
        ksort($session->payload);
        $this->sessions->addSearch(new PaymentCashierSessionCollection([$session]));
        $this->orders->expects($this->never())->method('create');

        $replayed = $this->cashier->create($this->app(), 'mini', $session->payload, new PaymentContext('CNY', new PaymentScene('browser', 'wap', 'browser'), [PaymentInteraction::REDIRECT]));

        static::assertSame($session, $replayed);
        static::assertSame([], $this->sessions->creates);
    }

    public function testIdempotentCreateRejectsChangedBusinessTerms(): void
    {
        $this->starter->expects($this->never())->method('start');
        $this->routes->expects($this->never())->method('resolveMethod');
        $session = $this->session();
        $this->sessions->addSearch(new PaymentCashierSessionCollection([$session]));
        $this->orders->expects($this->never())->method('create');

        $this->expectExceptionObject(PaymentCashierException::conflict($session->id));
        $this->cashier->create($this->app(), 'mini', ['out_trade_no' => 'purchase', 'amount' => 2999, 'subject' => 'Purchase'], new PaymentContext('CNY', new PaymentScene('browser', 'wap', 'browser')));
    }

    public function testReadRejectsForeignSessionWithoutDisclosingItsOrder(): void
    {
        $this->starter->expects($this->never())->method('start');
        $this->orders->expects($this->never())->method('getById');
        $this->routes->expects($this->never())->method('resolveMethod');
        $session = $this->session();
        $session->paymentAppId = 'other-app';
        $this->sessions->addSearch(new PaymentCashierSessionCollection([$session]));

        $this->expectExceptionObject(PaymentCashierException::unavailable($session->id));
        $this->cashier->get($this->app(), $session->id);
    }

    public function testReadRejectsExpiredSession(): void
    {
        $this->starter->expects($this->never())->method('start');
        $this->orders->expects($this->never())->method('getById');
        $this->routes->expects($this->never())->method('resolveMethod');
        $session = $this->session();
        $session->expireAt = new \DateTimeImmutable('2025-01-01T00:00:00Z');
        $this->sessions->addSearch(new PaymentCashierSessionCollection([$session]));

        $this->expectExceptionObject(PaymentCashierException::expired($session->id));
        $this->cashier->get($this->app(), $session->id);
    }

    public function testPayRejectsSwitchingMethodAfterOrderWasBound(): void
    {
        $this->starter->expects($this->never())->method('start');
        $this->orders->expects($this->never())->method('getById');
        $session = $this->session();
        $session->selectedMethod = 'card';
        $session->paymentOrderId = 'existing-order';
        $this->sessions->addSearch(new PaymentCashierSessionCollection([$session]));
        $this->routes->expects($this->never())->method('resolveMethod');

        $this->expectExceptionObject(PaymentCashierException::conflict($session->id));
        $this->cashier->pay($this->app(), $session->id, 'wallet');
    }

    public function testPayCreatesOrderFromFrozenPayloadAndStartsAfterSessionCommit(): void
    {
        $session = $this->session();
        $this->sessions->addSearch(new PaymentCashierSessionCollection([$session]));
        $route = $this->route();
        $order = $this->order();
        $this->routes->expects($this->once())->method('resolveMethod')->with($this->app(), 'card', static::equalTo($route->context), [], 'priority')->willReturn($route);
        $this->orders->expects($this->once())->method('create')->willReturnCallback(function (PaymentAppEntity $app, PaymentOrderRequest $request) use ($order): PaymentOrderEntity {
            static::assertTrue($this->locked);
            static::assertSame(1999, $request->amount);
            static::assertSame('immutable', $request->attach);
            static::assertSame('cashier:' . str_repeat('a', 32), $request->idempotencyKey);

            return $order;
        });
        $this->orders->method('isPayable')->willReturn(true);
        $this->orders->method('getById')->willReturn($order);
        $this->starter->method('replay')->willReturn(null);
        $this->starter->expects($this->once())->method('start')->willReturnCallback(function (): PaymentStartResult {
            static::assertFalse($this->locked);
            static::assertSame('card', $this->sessions->updates[0][0]['selectedMethod']);
            static::assertSame('order', $this->sessions->updates[0][0]['paymentOrderId']);

            return new PaymentStartResult(new PaymentHandover(PaymentInteraction::REDIRECT, 'https://pay.test'));
        });

        $payment = $this->cashier->pay($this->app(), $session->id, 'card');

        static::assertSame('P1', $payment['payment_no']);
        static::assertInstanceOf(PaymentHandover::class, $payment['pay']);
        static::assertSame('https://pay.test', $payment['pay']->payUrl);
    }

    public function testUnknownAttemptIsReplayedWithoutReroutingOrCreatingAnotherOrder(): void
    {
        $session = $this->session();
        $session->selectedMethod = 'card';
        $session->paymentOrderId = 'order';
        $this->sessions->addSearch(new PaymentCashierSessionCollection([$session]));
        $order = $this->order();
        $this->orders->method('getById')->willReturn($order);
        $this->orders->method('isPayable')->willReturn(true);
        $this->orders->expects($this->never())->method('create');
        $this->routes->expects($this->never())->method('resolveMethod');
        $this->starter->expects($this->never())->method('start');
        $this->starter->method('replay')->willThrowException(PaymentOrderException::startPending('P1'));

        $this->expectExceptionObject(PaymentOrderException::startPending('P1'));
        $this->cashier->pay($this->app(), $session->id, 'card');
    }

    private function session(): PaymentCashierSessionEntity
    {
        $session = new PaymentCashierSessionEntity();
        $session->id = str_repeat('a', 32);
        $session->setUniqueIdentifier($session->id);
        $session->paymentAppId = 'app';
        $session->outTradeNo = 'purchase';
        $session->cashierConfigId = 'config';
        $session->payload = ['out_trade_no' => 'purchase', 'amount' => 1999, 'currency' => 'CNY', 'subject' => 'Purchase', 'attach' => 'immutable'];
        $session->paymentContext = ['currency' => 'CNY', 'scene' => ['name' => 'browser', 'device' => 'wap', 'environment' => 'browser'], 'interactions' => [PaymentInteraction::REDIRECT], 'sdk_products' => [], 'runtime_params' => [], 'account_runtime_params' => []];
        $session->allowedMethods = ['card'];
        $session->routingPolicy = 'priority';
        $session->expireAt = new \DateTimeImmutable('2026-01-01T00:30:00Z');

        return $session;
    }

    private function app(): PaymentAppEntity
    {
        $app = new PaymentAppEntity();
        $app->id = 'app';

        return $app;
    }

    private function order(): PaymentOrderEntity
    {
        $order = new PaymentOrderEntity();
        $order->id = 'order';
        $order->paymentNo = 'P1';
        $order->outTradeNo = 'purchase';
        $order->amount = 1999;
        $order->currency = 'CNY';
        $order->subject = 'Purchase';
        $order->stateId = 'created';

        return $order;
    }

    private function route(): PaymentRoute
    {
        $product = new PaymentProduct('plugin_web', 'plugin', 'Plugin', [PaymentInteraction::REDIRECT], 'card');
        $provider = new PaymentProvider('plugin', 'Plugin', [], [$product->technicalName => $product], static::createStub(PaymentGatewayFactory::class), static::createStub(PaymentNotificationExtractorInterface::class));
        $config = new ResolvedPaymentConfig($provider, $product, [], [], [], [], 'account');

        return new PaymentRoute($config, new PaymentContext('CNY', new PaymentScene('browser', 'wap', 'browser'), [PaymentInteraction::REDIRECT]), PaymentInteraction::REDIRECT);
    }
}
