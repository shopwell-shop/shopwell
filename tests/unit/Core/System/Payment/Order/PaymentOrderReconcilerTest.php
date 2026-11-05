<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Order;

use Doctrine\DBAL\Connection;
use Payum\Core\GatewayInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\App\PaymentCredentialCipher;
use Shopwell\Core\System\Payment\App\PaymentSignatureService;
use Shopwell\Core\System\Payment\Exception\PaymentProviderException;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayFactory;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationExtractorInterface;
use Shopwell\Core\System\Payment\Order\Aggregate\PaymentAttempt\PaymentAttemptCollection;
use Shopwell\Core\System\Payment\Order\Aggregate\PaymentAttempt\PaymentAttemptEntity;
use Shopwell\Core\System\Payment\Order\PaymentOrderCollection;
use Shopwell\Core\System\Payment\Order\PaymentOrderEntity;
use Shopwell\Core\System\Payment\Order\PaymentOrderPaidHandler;
use Shopwell\Core\System\Payment\Order\PaymentOrderQueryResultHandler;
use Shopwell\Core\System\Payment\Order\PaymentOrderReconciler;
use Shopwell\Core\System\Payment\Order\PaymentOrderService;
use Shopwell\Core\System\Payment\Order\PaymentOrderStateHandler;
use Shopwell\Core\System\Payment\Product\PaymentInteraction;
use Shopwell\Core\System\Payment\Product\PaymentProduct;
use Shopwell\Core\System\Payment\Provider\Capability\PaymentCapability;
use Shopwell\Core\System\Payment\Provider\Config\PaymentConfigResolver;
use Shopwell\Core\System\Payment\Provider\PaymentProvider;
use Shopwell\Core\System\Payment\Provider\ResolvedPaymentConfig;
use Shopwell\Core\System\Payment\Webhook\PaymentWebhookDispatcher;
use Shopwell\Core\System\Payment\Webhook\PaymentWebhookSigner;
use Shopwell\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateCollection;
use Shopwell\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopwell\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Shopwell\Core\System\StateMachine\StateMachineRegistry;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopwell\Tests\Unit\Core\System\Payment\NumberRangeFixture;
use Shopwell\Tests\Unit\Core\System\Payment\Order\_fixtures\PaymentOrderRowLockStub;
use Shopwell\Tests\Unit\Core\System\Payment\PaymentEventDispatcherFixture;
use Symfony\Component\Clock\MockClock;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * What the reconciliation may ask of an interface: the declaration answers it before a request is
 * signed, so an interface that cannot be queried is refused instead of being reported as a broken
 * channel. The positive direction - a query of an interface that declares it - is covered by
 * {@see PaymentOrderCloserTest}, whose paid-trade path reconciles through this service.
 *
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentOrderReconciler::class)]
class PaymentOrderReconcilerTest extends TestCase
{
    private const NOW = '2024-01-01T00:00:00+00:00';

    /**
     * An interface that never declared a query is refused before the channel is asked: payum would
     * otherwise fail inside the gateway with "no action supports this request", which reads as a
     * channel fault (502) although the declaration answered the question already - and the order would
     * be retried against an interface that can never answer.
     */
    public function testAnUnqueryableOrderYieldsItsBatchPosition(): void
    {
        $first = $this->order();
        $first->id = 'first';
        $first->paymentApp = null;
        $second = $this->order();
        $second->id = 'second';
        $second->paymentApp = null;
        $select = static function (Criteria $criteria, Context $context, StaticEntityRepository $repository) use ($first, $second): PaymentOrderCollection {
            static::assertSame(['updatedAt', 'id'], array_map(static fn (FieldSorting $sort): string => $sort->getField(), array_values($criteria->getSorting())));
            $alreadyAsked = array_column(array_merge(...$repository->updates), 'id');

            return new PaymentOrderCollection([\in_array('first', $alreadyAsked, true) ? $second : $first]);
        };
        $repository = StaticEntityRepository::of(PaymentOrderCollection::class, [$select, $select]);
        $opened = new StateMachineStateEntity();
        $opened->setId('state-opened');
        $states = new StaticEntityRepository([new StateMachineStateCollection([$opened]), new StateMachineStateCollection([$opened])]);
        $reconciler = $this->reconciler(static::createStub(GatewayInterface::class), [], $repository, $states);

        static::assertSame(0, $reconciler->reconcileDue(1));
        static::assertSame(0, $reconciler->reconcileDue(1));
        static::assertSame('first', $repository->updates[0][0]['id']);
        static::assertSame('second', $repository->updates[1][0]['id']);
        static::assertEquals(new \DateTimeImmutable(self::NOW), $repository->updates[0][0]['updatedAt']);
    }

    public function testAnInterfaceWithoutQueryIsRefusedBeforeTheChannelIsAsked(): void
    {
        $gateway = static::createMock(GatewayInterface::class);
        $gateway->expects($this->never())->method('execute');

        $this->expectExceptionObject(PaymentProviderException::providerLacksCapability('alipay', PaymentCapability::QUERY->value));

        $this->reconciler($gateway, [PaymentCapability::CAPTURE])->reconcile($this->order());
    }

    public function testProductWithoutQueryIsRefusedWhenTheProviderSupportsIt(): void
    {
        $gateway = static::createMock(GatewayInterface::class);
        $gateway->expects($this->never())->method('execute');
        $this->expectExceptionObject(PaymentProviderException::providerLacksCapability('alipay', PaymentCapability::QUERY->value));

        $this->reconciler($gateway, [PaymentCapability::QUERY], productCapabilities: [PaymentCapability::CAPTURE])->reconcile($this->order());
    }

    /**
     * @param list<PaymentCapability> $capabilities
     * @param list<PaymentCapability>|null $productCapabilities
     * @param EntityRepository<PaymentOrderCollection>|null $orders
     * @param EntityRepository<StateMachineStateCollection>|null $states
     */
    private function reconciler(GatewayInterface $gateway, array $capabilities, ?EntityRepository $orders = null, ?EntityRepository $states = null, ?array $productCapabilities = null): PaymentOrderReconciler
    {
        $configResolver = $this->configResolver($gateway, $capabilities, $productCapabilities);
        $clock = new MockClock(self::NOW);

        $initialStateIdLoader = static::createStub(InitialStateIdLoader::class);
        $initialStateIdLoader->method('get')->willReturn('state-created');

        $orderRepository = $orders ?? static::createStub(EntityRepository::class);

        $orderService = new PaymentOrderService(
            $orderRepository,
            $initialStateIdLoader,
            new PaymentOrderStateHandler(static::createStub(StateMachineRegistry::class)),
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

        return new PaymentOrderReconciler(
            $orderRepository,
            $states ?? static::createStub(EntityRepository::class),
            $configResolver,
            $orderService,
            $paidHandler,
            new PaymentOrderQueryResultHandler($orderService, $webhooks, $paidHandler, $clock),
            $clock,
            static::createStub(LoggerInterface::class),
        );
    }

    /**
     * @param list<PaymentCapability> $capabilities
     * @param list<PaymentCapability>|null $productCapabilities
     */
    private function configResolver(GatewayInterface $gateway, array $capabilities, ?array $productCapabilities = null): PaymentConfigResolver
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

    private function order(): PaymentOrderEntity
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
