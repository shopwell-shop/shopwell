<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Order\_fixtures;

use Doctrine\DBAL\Connection;
use Payum\Core\GatewayInterface;
use PHPUnit\Framework\Attributes\CoversNothing;
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
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayFactory;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationExtractorInterface;
use Shopwell\Core\System\Payment\Order\PaymentOrderCollection;
use Shopwell\Core\System\Payment\Order\PaymentOrderEntity;
use Shopwell\Core\System\Payment\Order\PaymentOrderPaidHandler;
use Shopwell\Core\System\Payment\Order\PaymentOrderQueryResultHandler;
use Shopwell\Core\System\Payment\Order\PaymentOrderReconciler;
use Shopwell\Core\System\Payment\Order\PaymentOrderService;
use Shopwell\Core\System\Payment\Order\PaymentOrderStateHandler;
use Shopwell\Core\System\Payment\Product\PaymentInteraction;
use Shopwell\Core\System\Payment\Product\PaymentProduct;
use Shopwell\Core\System\Payment\Product\PaymentProductRegistry;
use Shopwell\Core\System\Payment\Provider\Config\PaymentConfigResolver;
use Shopwell\Core\System\Payment\Provider\PaymentProvider;
use Shopwell\Core\System\Payment\Provider\PaymentProviderRegistry;
use Shopwell\Core\System\Payment\Webhook\PaymentWebhookDeliveryCollection;
use Shopwell\Core\System\Payment\Webhook\PaymentWebhookDeliveryEntity;
use Shopwell\Core\System\Payment\Webhook\PaymentWebhookDispatcher;
use Shopwell\Core\System\Payment\Webhook\PaymentWebhookSigner;
use Shopwell\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateCollection;
use Shopwell\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopwell\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Shopwell\Core\System\StateMachine\StateMachineRegistry;
use Shopwell\Tests\Unit\Core\System\Payment\NumberRangeFixture;
use Shopwell\Tests\Unit\Core\System\Payment\PaymentEventDispatcherFixture;
use Symfony\Component\Clock\MockClock;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversNothing]
class PaymentOrderReconcilerFixture extends TestCase
{
    /**
     * @param EntityRepository<PaymentOrderCollection> $orderRepository
     * @param EntityRepository<StateMachineStateCollection> $stateRepository
     * @param EntityRepository<PaymentWebhookDeliveryCollection>|null $deliveryRepository
     */
    public static function reconciler(
        EntityRepository $orderRepository,
        EntityRepository $stateRepository,
        ?EventDispatcherInterface $eventDispatcher = null,
        ?EntityRepository $deliveryRepository = null
    ): PaymentOrderReconciler {
        $gatewayFactory = static::createStub(PaymentGatewayFactory::class);
        $gatewayFactory->method('createForPayment')->willReturn(static::createStub(GatewayInterface::class));

        $channel = new PaymentProduct('alipay_h5', 'alipay', 'Alipay H5', [PaymentInteraction::REDIRECT], 'alipay');

        $provider = new PaymentProvider(
            'alipay',
            'Alipay',
            [],
            ['alipay_h5' => $channel],
            $gatewayFactory,
            static::createStub(PaymentNotificationExtractorInterface::class),
        );

        $providerRegistry = new PaymentProviderRegistry([$provider]);

        $configResolver = new PaymentConfigResolver(
            $providerRegistry,
            new PaymentProductRegistry($providerRegistry),
            static::createStub(EntityRepository::class),
            static::createStub(EntityRepository::class),
            new PaymentCredentialCipher('test-secret'),
            static::createStub(EntityRepository::class)
        );

        $initialStateIdLoader = static::createStub(InitialStateIdLoader::class);
        $initialStateIdLoader->method('get')->willReturn('state-created');

        $orderService = new PaymentOrderService(
            $orderRepository,
            $initialStateIdLoader,
            new PaymentOrderStateHandler(static::createStub(StateMachineRegistry::class)),
            new MockClock('2024-01-01T00:00:00+00:00'),
            NumberRangeFixture::generator('PG1001'),
            new PaymentOrderRowLockStub(),
            static::createStub(EntityRepository::class)
        );

        $webhookService = new PaymentWebhookDispatcher(
            $deliveryRepository ?? static::createStub(EntityRepository::class),
            self::appRepository(),
            new PaymentWebhookSigner(new PaymentSignatureService(), new PaymentCredentialCipher('test-secret'), new MockClock('2024-01-01T00:00:00+00:00')),
            static::createStub(HttpClientInterface::class),
            new MockClock('2024-01-01T00:00:00+00:00'),
            static::createStub(LoggerInterface::class),
            static::createStub(Connection::class)
        );

        $clock = new MockClock('2024-01-01T00:00:00+00:00');
        $paidHandler = new PaymentOrderPaidHandler($orderService, PaymentEventDispatcherFixture::create($webhookService, $eventDispatcher));

        return new PaymentOrderReconciler(
            $orderRepository,
            $stateRepository,
            $configResolver,
            $orderService,
            $paidHandler,
            new PaymentOrderQueryResultHandler($orderService, $webhookService, $paidHandler, $clock),
            $clock,
            static::createStub(LoggerInterface::class)
        );
    }

    /**
     * @return EntityRepository<PaymentAppCollection>
     */
    public static function appRepository(): EntityRepository
    {
        $repository = static::createStub(EntityRepository::class);
        $repository->method('search')->willReturn(new EntitySearchResult(
            'payment_app',
            1,
            new PaymentAppCollection([self::app()]),
            null,
            new Criteria(),
            Context::createDefaultContext()
        ));

        return $repository;
    }

    public static function app(): PaymentAppEntity
    {
        $app = new PaymentAppEntity();
        $app->setUniqueIdentifier('app-id');
        $app->id = 'app-id';
        $app->technicalName = 'demo-app';
        $app->appSecret = (new PaymentCredentialCipher('test-secret'))->encryptValue('secret');
        $app->active = true;

        return $app;
    }

    /**
     * @param array<string, mixed> $overrides
     */
    public static function order(array $overrides = []): PaymentOrderEntity
    {
        $order = new PaymentOrderEntity();
        $order->setUniqueIdentifier('order-id');
        $order->id = 'order-id';
        $order->paymentNo = 'PG0001';
        $order->outTradeNo = 'app-trade-1';
        $order->paymentAppId = 'app-id';

        $order->amount = 1000;
        $order->paidAmount = 1000;
        $order->refundedAmount = 0;
        $order->currency = 'CNY';
        $order->subject = 'An order';
        $order->stateId = 'state-opened';
        $order->notifyUrl = 'https://app.example.test/webhook';
        $order->paymentApp = self::app();

        if (\array_key_exists('stateId', $overrides)) {
            $order->stateId = (string) $overrides['stateId'];
        }

        if (\array_key_exists('paidAt', $overrides)) {
            $paidAt = $overrides['paidAt'];
            static::assertInstanceOf(\DateTimeInterface::class, $paidAt);
            $order->paidAt = $paidAt;
        }

        if (\array_key_exists('closedAt', $overrides)) {
            $closedAt = $overrides['closedAt'];
            static::assertInstanceOf(\DateTimeInterface::class, $closedAt);
            $order->closedAt = $closedAt;
        }

        return $order;
    }

    /**
     * @return EntitySearchResult<PaymentWebhookDeliveryCollection>
     */
    public static function deliverySearchResult(PaymentWebhookDeliveryEntity ...$deliveries): EntitySearchResult
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

    public static function delivery(): PaymentWebhookDeliveryEntity
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

    public static function openedState(): StateMachineStateEntity
    {
        $state = new StateMachineStateEntity();
        $state->setId('state-opened');

        return $state;
    }
}
