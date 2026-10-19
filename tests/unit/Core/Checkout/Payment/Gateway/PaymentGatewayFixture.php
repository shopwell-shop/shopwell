<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Payment\Gateway;

use Payum\Core\GatewayInterface;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopwell\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopwell\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopwell\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopwell\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopwell\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefund\OrderTransactionCaptureRefundEntity;
use Shopwell\Core\Checkout\Order\OrderEntity;
use Shopwell\Core\Checkout\Payment\Gateway\OrderPaymentStarter;
use Shopwell\Core\Checkout\Payment\Storefront\PlatformPaymentApp;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Currency\CurrencyEntity;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppChannel\PaymentAppChannelEntity;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppProvider\PaymentAppProviderEntity;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\App\PaymentCredentialCipher;
use Shopwell\Core\System\Payment\App\PaymentSignatureService;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayFactory;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationExtractorInterface;
use Shopwell\Core\System\Payment\Notification\PaymentNotifyUrlBuilder;
use Shopwell\Core\System\Payment\Order\Aggregate\PaymentTransaction\PaymentTransactionCollection;
use Shopwell\Core\System\Payment\Order\Aggregate\PaymentTransaction\PaymentTransactionEntity;
use Shopwell\Core\System\Payment\Order\PaymentOrderCapture;
use Shopwell\Core\System\Payment\Order\PaymentOrderCollection;
use Shopwell\Core\System\Payment\Order\PaymentOrderEntity;
use Shopwell\Core\System\Payment\Order\PaymentOrderPaidHandler;
use Shopwell\Core\System\Payment\Order\PaymentOrderQueryResultHandler;
use Shopwell\Core\System\Payment\Order\PaymentOrderReconciler;
use Shopwell\Core\System\Payment\Order\PaymentOrderService;
use Shopwell\Core\System\Payment\Order\PaymentOrderStateHandler;
use Shopwell\Core\System\Payment\Order\Refund\PaymentRefundCollection;
use Shopwell\Core\System\Payment\Order\Refund\PaymentRefundService;
use Shopwell\Core\System\Payment\Order\Refund\PaymentRefundStateHandler;
use Shopwell\Core\System\Payment\Provider\Alipay\AlipayPayParamsDecoder;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentChannel;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentChannelRegistry;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentFlow;
use Shopwell\Core\System\Payment\Provider\PaymentChannelConfigEntity;
use Shopwell\Core\System\Payment\Provider\PaymentConfigResolver;
use Shopwell\Core\System\Payment\Provider\PaymentProvider;
use Shopwell\Core\System\Payment\Provider\PaymentProviderConfigEntity;
use Shopwell\Core\System\Payment\Provider\PaymentProviderRegistry;
use Shopwell\Core\System\Payment\Webhook\PaymentWebhookDispatcher;
use Shopwell\Core\System\Payment\Webhook\PaymentWebhookSigner;
use Shopwell\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Shopwell\Core\System\StateMachine\StateMachineRegistry;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Wires the real services of the gateway handler together with stubbed repositories and a stubbed
 * channel gateway.
 *
 * The final services (the order service, the capture, the platform tenant, the reconciler and the
 * refund service) are built for real on purpose: the tests exercise the path the checkout really
 * runs, only the repositories and payum are replaced.
 *
 * @internal
 */
#[Package('checkout')]
#[CoversNothing]
final class PaymentGatewayFixture extends TestCase
{
    public const PLATFORM_APP_ID = 'platform-app-id';

    public const CHANNEL = 'alipay_h5';

    public const PROVIDER = 'alipay';

    private const CIPHER_SECRET = 'unit-test-cipher-secret';

    private const NOW = '2026-01-01 12:00:00';

    public static function clock(): MockClock
    {
        return new MockClock(self::NOW);
    }

    public static function cipher(): PaymentCredentialCipher
    {
        return new PaymentCredentialCipher(self::CIPHER_SECRET);
    }

    /**
     * A repository that answers every search with the given entities.
     *
     * @param list<Entity> $entities
     *
     * @phpstan-ignore missingType.generics (The stub serves every collection type the caller asserts.)
     */
    public static function repository(array $entities = []): EntityRepository
    {
        $repository = static::createStub(EntityRepository::class);
        $repository->method('search')->willReturn(self::searchResult($entities));

        return $repository;
    }

    /**
     * @param list<Entity> $entities
     *
     * @return EntitySearchResult<EntityCollection<Entity>>
     */
    public static function searchResult(array $entities): EntitySearchResult
    {
        $first = $entities[0] ?? null;

        return new EntitySearchResult(
            $first instanceof Entity ? $first->getApiAlias() : 'test_entity',
            \count($entities),
            new EntityCollection($entities),
            null,
            new Criteria(),
            Context::createDefaultContext()
        );
    }

    /**
     * @param list<string> $devices
     */
    public static function channel(PaymentFlow $flow = PaymentFlow::REDIRECT, array $devices = []): PaymentChannel
    {
        return new PaymentChannel(
            technicalName: self::CHANNEL,
            providerTechnicalName: self::PROVIDER,
            label: 'Alipay H5',
            flow: $flow,
            devices: $devices,
        );
    }

    /**
     * @param list<string> $devices
     */
    public static function channelRegistry(PaymentFlow $flow = PaymentFlow::REDIRECT, array $devices = []): PaymentChannelRegistry
    {
        return new PaymentChannelRegistry(self::providerRegistry($flow, devices: $devices));
    }

    /**
     * A provider registry whose only interface hands out the given gateway on every payment.
     *
     * @param list<string> $devices
     */
    public static function providerRegistry(
        PaymentFlow $flow = PaymentFlow::REDIRECT,
        ?GatewayInterface $gateway = null,
        array $devices = []
    ): PaymentProviderRegistry {
        $gatewayFactory = static::createStub(PaymentGatewayFactory::class);

        if ($gateway instanceof GatewayInterface) {
            $gatewayFactory->method('createForPayment')->willReturn($gateway);
        }

        return new PaymentProviderRegistry([
            new PaymentProvider(
                technicalName: self::PROVIDER,
                label: 'Alipay',
                credentials: [],
                channels: [self::CHANNEL => self::channel($flow, $devices)],
                gatewayFactory: $gatewayFactory,
                notificationExtractor: static::createStub(PaymentNotificationExtractorInterface::class),
                payParamsDecoder: new AlipayPayParamsDecoder(),
            ),
        ]);
    }

    /**
     * The tenant the shop collects through: the migration seeds it, the runtime only reads it and
     * fills in the authorizations of the channels that are actually used.
     */
    public static function platformApp(): PlatformPaymentApp
    {
        return new PlatformPaymentApp(
            self::repository([self::platformAppEntity()]),
            self::repository([self::appProvider()]),
            self::repository([self::appChannel()]),
            self::repository([self::providerConfig()]),
            self::repository([self::channelConfig()]),
            new PaymentChannelRegistry(self::providerRegistry()),
            self::providerRegistry(),
        );
    }

    public static function platformAppEntity(): PaymentAppEntity
    {
        $app = new PaymentAppEntity();
        $app->id = self::PLATFORM_APP_ID;
        $app->setUniqueIdentifier(self::PLATFORM_APP_ID);
        $app->technicalName = PaymentAppEntity::PLATFORM_TECHNICAL_NAME;
        $app->name = 'Shopfront';
        $app->type = PaymentAppEntity::TYPE_PLATFORM;
        $app->active = true;
        $app->appSecret = self::cipher()->encryptValue('unreadable');
        $app->settings = [];

        return $app;
    }

    /**
     * An app that was onboarded by the merchant: it collects on its own merchant account and offers
     * only the channels the merchant switched on for it.
     */
    public static function externalApp(string $id = 'external-app-id', bool $active = true): PaymentAppEntity
    {
        $app = new PaymentAppEntity();
        $app->id = $id;
        $app->setUniqueIdentifier($id);
        $app->technicalName = 'external-app';
        $app->name = 'External app';
        $app->type = PaymentAppEntity::TYPE_EXTERNAL;
        $app->active = $active;
        $app->appSecret = self::cipher()->encryptValue('unreadable');
        $app->settings = [];

        return $app;
    }

    public static function appProvider(): PaymentAppProviderEntity
    {
        $appProvider = new PaymentAppProviderEntity();
        $appProvider->id = 'app-provider-id';
        $appProvider->setUniqueIdentifier($appProvider->id);
        $appProvider->paymentAppId = self::PLATFORM_APP_ID;
        $appProvider->providerTechnicalName = self::PROVIDER;
        $appProvider->enabled = true;
        $appProvider->mode = PaymentAppProviderEntity::MODE_PLATFORM;

        return $appProvider;
    }

    public static function appChannel(): PaymentAppChannelEntity
    {
        $appChannel = new PaymentAppChannelEntity();
        $appChannel->id = 'app-channel-id';
        $appChannel->setUniqueIdentifier($appChannel->id);
        $appChannel->paymentAppId = self::PLATFORM_APP_ID;
        $appChannel->channelTechnicalName = self::CHANNEL;
        $appChannel->enabled = true;

        return $appChannel;
    }

    public static function providerConfig(): PaymentProviderConfigEntity
    {
        $providerConfig = new PaymentProviderConfigEntity();
        $providerConfig->id = 'provider-config-id';
        $providerConfig->setUniqueIdentifier($providerConfig->id);
        $providerConfig->providerTechnicalName = self::PROVIDER;
        $providerConfig->enabled = true;
        $providerConfig->credentials = self::cipher()->encrypt(['app_id' => 'platform-app-id']);

        return $providerConfig;
    }

    public static function channelConfig(): PaymentChannelConfigEntity
    {
        $channelConfig = new PaymentChannelConfigEntity();
        $channelConfig->id = 'channel-config-id';
        $channelConfig->setUniqueIdentifier($channelConfig->id);
        $channelConfig->providerTechnicalName = self::PROVIDER;
        $channelConfig->channelTechnicalName = self::CHANNEL;
        $channelConfig->enabled = true;

        return $channelConfig;
    }

    public static function configResolver(
        ?GatewayInterface $gateway = null,
        PaymentFlow $flow = PaymentFlow::REDIRECT
    ): PaymentConfigResolver {
        $providerRegistry = self::providerRegistry($flow, $gateway);

        return new PaymentConfigResolver(
            $providerRegistry,
            new PaymentChannelRegistry($providerRegistry),
            self::repository([self::appProvider()]),
            self::repository([self::providerConfig()]),
            self::repository([self::appChannel()]),
            self::repository([self::channelConfig()]),
            self::cipher(),
        );
    }

    /**
     * @param EntityRepository<PaymentOrderCollection> $paymentOrderRepository
     */
    public static function paymentOrderService(
        EntityRepository $paymentOrderRepository,
        ?PaymentConfigResolver $configResolver = null
    ): PaymentOrderService {
        $initialStateIdLoader = static::createStub(InitialStateIdLoader::class);
        $initialStateIdLoader->method('get')->willReturn(self::createdStateId());

        return new PaymentOrderService(
            $paymentOrderRepository,
            $configResolver ?? self::configResolver(),
            $initialStateIdLoader,
            new PaymentOrderStateHandler(static::createStub(StateMachineRegistry::class)),
            self::clock(),
        );
    }

    /**
     * @param EntityRepository<PaymentTransactionCollection> $transactionRepository
     */
    public static function captureService(
        EntityRepository $transactionRepository,
        PaymentConfigResolver $configResolver,
        PaymentOrderService $orderService
    ): PaymentOrderCapture {
        return new PaymentOrderCapture(
            $transactionRepository,
            $configResolver,
            $orderService,
            new PaymentNotifyUrlBuilder(new RequestStack(), 'https://shop.example.test'),
            self::clock(),
        );
    }

    /**
     * @param EntityRepository<PaymentOrderCollection> $paymentOrderRepository
     * @param EntityRepository<PaymentTransactionCollection> $transactionRepository
     */
    public static function starter(
        EntityRepository $paymentOrderRepository,
        EntityRepository $transactionRepository,
        ?GatewayInterface $gateway = null,
        PaymentFlow $flow = PaymentFlow::REDIRECT
    ): OrderPaymentStarter {
        $configResolver = self::configResolver($gateway, $flow);
        $orderService = self::paymentOrderService($paymentOrderRepository, $configResolver);

        return new OrderPaymentStarter(
            self::platformApp(),
            self::providerRegistry($flow),
            $orderService,
            self::captureService($transactionRepository, $configResolver, $orderService),
            self::router(),
        );
    }

    public static function router(): RouterInterface
    {
        $router = static::createStub(RouterInterface::class);
        $router->method('generate')->willReturnCallback(
            static fn (string $name, array $parameters = []): string => '/generated/' . $name . '/' . (string) ($parameters['paymentNo'] ?? '')
        );

        return $router;
    }

    /**
     * @param EntityRepository<PaymentOrderCollection> $paymentOrderRepository
     */
    public static function reconciler(
        EntityRepository $paymentOrderRepository,
        PaymentOrderService $orderService,
        ?PaymentConfigResolver $configResolver = null
    ): PaymentOrderReconciler {
        $webhooks = self::webhookService();
        $paidHandler = new PaymentOrderPaidHandler(
            $orderService,
            $webhooks,
            static::createStub(EventDispatcherInterface::class)
        );

        return new PaymentOrderReconciler(
            $paymentOrderRepository,
            self::repository(),
            $configResolver ?? self::configResolver(),
            $orderService,
            $paidHandler,
            new PaymentOrderQueryResultHandler($orderService, $webhooks, $paidHandler, self::clock()),
            self::clock(),
            static::createStub(LoggerInterface::class),
        );
    }

    public static function webhookService(): PaymentWebhookDispatcher
    {
        return new PaymentWebhookDispatcher(
            self::repository(),
            self::repository(),
            new PaymentWebhookSigner(new PaymentSignatureService(), self::cipher(), self::clock()),
            static::createStub(HttpClientInterface::class),
            self::clock(),
            static::createStub(LoggerInterface::class),
        );
    }

    /**
     * @param EntityRepository<PaymentRefundCollection> $refundRepository
     */
    public static function refundService(
        EntityRepository $refundRepository,
        PaymentOrderService $orderService,
        ?PaymentConfigResolver $configResolver = null
    ): PaymentRefundService {
        $configResolver ??= self::configResolver();

        return new PaymentRefundService(
            $refundRepository,
            $configResolver,
            $orderService,
            self::webhookService(),
            new PaymentNotifyUrlBuilder(new RequestStack(), 'https://shop.example.test'),
            new PaymentRefundStateHandler(static::createStub(StateMachineRegistry::class)),
            static::createStub(InitialStateIdLoader::class),
            static::createStub(EventDispatcherInterface::class),
            self::clock(),
        );
    }

    public static function createdStateId(): string
    {
        return 'state-created';
    }

    /**
     * @param array<string, mixed> $overrides
     */
    public static function paymentOrder(array $overrides = []): PaymentOrderEntity
    {
        $order = new PaymentOrderEntity();
        $order->id = 'payment-order-id';
        $order->setUniqueIdentifier($order->id);
        $order->paymentNo = 'PG-1';
        $order->outTradeNo = 'order-transaction-id';
        $order->paymentAppId = self::PLATFORM_APP_ID;
        $order->orderId = 'order-id';
        $order->orderTransactionId = 'order-transaction-id';
        $order->providerTechnicalName = self::PROVIDER;
        $order->channelTechnicalName = self::CHANNEL;
        $order->amount = 1999;
        $order->currency = 'EUR';
        $order->subject = 'SW-1000';
        $order->stateId = self::createdStateId();

        foreach ($overrides as $property => $value) {
            // @phpstan-ignore property.dynamicName (the overrides name the properties of the entity on purpose)
            $order->$property = $value;
        }

        return $order;
    }

    /**
     * @param array<string, mixed>|null $response
     */
    public static function captureTransaction(?array $response): PaymentTransactionEntity
    {
        $transaction = new PaymentTransactionEntity();
        $transaction->id = 'payment-transaction-id';
        $transaction->setUniqueIdentifier($transaction->id);
        $transaction->paymentOrderId = 'payment-order-id';
        $transaction->transactionNo = 'PT-1';
        $transaction->type = PaymentTransactionEntity::TYPE_CAPTURE;
        $transaction->providerTechnicalName = self::PROVIDER;
        $transaction->channelTechnicalName = self::CHANNEL;
        $transaction->status = PaymentTransactionEntity::STATUS_SUCCESS;
        $transaction->responsePayload = $response;

        return $transaction;
    }

    public static function currency(string $isoCode = 'EUR', int $decimals = 2): CurrencyEntity
    {
        return (new CurrencyEntity())->assign([
            'id' => 'currency-id',
            'isoCode' => $isoCode,
            'factor' => 1.0,
            'symbol' => '€',
            'position' => 1,
            'itemRounding' => new CashRoundingConfig($decimals, 0.01, true),
            'totalRounding' => new CashRoundingConfig($decimals, 0.01, true),
        ]);
    }

    public static function order(): OrderEntity
    {
        return (new OrderEntity())->assign([
            'id' => 'order-id',
            'orderNumber' => 'SW-1000',
            'currencyId' => 'currency-id',
            'currency' => self::currency(),
        ]);
    }

    public static function orderTransaction(OrderEntity $order): OrderTransactionEntity
    {
        return (new OrderTransactionEntity())->assign([
            'id' => 'order-transaction-id',
            'orderId' => $order->getId(),
            'orderVersionId' => 'order-version-id',
            'paymentMethodId' => 'payment-method-id',
            'stateId' => self::createdStateId(),
            // 19.99 EUR in the smallest unit
            'amount' => new CalculatedPrice(
                19.99,
                19.99,
                new CalculatedTaxCollection(),
                new TaxRuleCollection()
            ),
            'order' => $order,
        ]);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    public static function captureRefund(array $overrides = []): OrderTransactionCaptureRefundEntity
    {
        $refund = (new OrderTransactionCaptureRefundEntity())->assign([
            'id' => 'refund-id',
            'captureId' => 'capture-id',
            'captureVersionId' => 'capture-version-id',
            'stateId' => 'state-open',
            'reason' => 'customer returned the goods',
            'amount' => new CalculatedPrice(
                5.5,
                5.5,
                new CalculatedTaxCollection(),
                new TaxRuleCollection()
            ),
        ]);

        foreach ($overrides as $property => $value) {
            // @phpstan-ignore property.dynamicName (the overrides name the properties of the entity on purpose)
            $refund->$property = $value;
        }

        return $refund;
    }
}
