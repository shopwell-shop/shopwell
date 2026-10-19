<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Api;

use Payum\Core\GatewayInterface;
use Payum\Core\Request\Generic;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppChannel\PaymentAppChannelCollection;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppChannel\PaymentAppChannelEntity;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppProvider\PaymentAppProviderCollection;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppProvider\PaymentAppProviderEntity;
use Shopwell\Core\System\Payment\App\PaymentAppAuthenticator;
use Shopwell\Core\System\Payment\App\PaymentAppCollection;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\App\PaymentCredentialCipher;
use Shopwell\Core\System\Payment\App\PaymentSignatureService;
use Shopwell\Core\System\Payment\Gateway\CloseResult;
use Shopwell\Core\System\Payment\Gateway\PaymentCloser;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayFactory;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationCollection;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationExtractorInterface;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationProcessor;
use Shopwell\Core\System\Payment\Notification\PaymentNotifyUrlBuilder;
use Shopwell\Core\System\Payment\Order\Aggregate\PaymentTransaction\PaymentTransactionCollection;
use Shopwell\Core\System\Payment\Order\PaymentOrderCapture;
use Shopwell\Core\System\Payment\Order\PaymentOrderCloser;
use Shopwell\Core\System\Payment\Order\PaymentOrderCollection;
use Shopwell\Core\System\Payment\Order\PaymentOrderEntity;
use Shopwell\Core\System\Payment\Order\PaymentOrderPaidHandler;
use Shopwell\Core\System\Payment\Order\PaymentOrderPresenter;
use Shopwell\Core\System\Payment\Order\PaymentOrderQueryResultHandler;
use Shopwell\Core\System\Payment\Order\PaymentOrderReconciler;
use Shopwell\Core\System\Payment\Order\PaymentOrderService;
use Shopwell\Core\System\Payment\Order\PaymentOrderStateHandler;
use Shopwell\Core\System\Payment\Order\Refund\PaymentRefundCollection;
use Shopwell\Core\System\Payment\Order\Refund\PaymentRefundEntity;
use Shopwell\Core\System\Payment\Order\Refund\PaymentRefundService;
use Shopwell\Core\System\Payment\Order\Refund\PaymentRefundStateHandler;
use Shopwell\Core\System\Payment\Provider\Capability\PaymentCapability;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentChannel;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentChannelRegistry;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentFlow;
use Shopwell\Core\System\Payment\Provider\PaymentChannelConfigCollection;
use Shopwell\Core\System\Payment\Provider\PaymentChannelConfigEntity;
use Shopwell\Core\System\Payment\Provider\PaymentConfigResolver;
use Shopwell\Core\System\Payment\Provider\PaymentProvider;
use Shopwell\Core\System\Payment\Provider\PaymentProviderConfigCollection;
use Shopwell\Core\System\Payment\Provider\PaymentProviderConfigEntity;
use Shopwell\Core\System\Payment\Provider\PaymentProviderRegistry;
use Shopwell\Core\System\Payment\Webhook\PaymentWebhookDeliveryCollection;
use Shopwell\Core\System\Payment\Webhook\PaymentWebhookDispatcher;
use Shopwell\Core\System\Payment\Webhook\PaymentWebhookSigner;
use Shopwell\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateCollection;
use Shopwell\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopwell\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Shopwell\Core\System\StateMachine\StateMachineRegistry;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Builds the heavy, final services of the payment api by hand so that they can be used with stubbed
 * leaf dependencies in the unit tests - the services themselves cannot be doubled.
 *
 * Every method returns a fresh object graph and keeps no state between calls. The class extends the
 * test case for one reason only: the doubles are created through its protected factory. No instance of
 * it is ever built and the test suite does not pick it up, as only `*Test.php` files are collected.
 *
 * @internal
 */
#[Package('framework')]
#[CoversNothing]
final class PaymentControllerFixture extends TestCase
{
    private const CIPHER_SECRET = 'unit-test-cipher-secret';

    /**
     * Everything the four interfaces of the platform can do between them - the fixture models an
     * interface that is asked for a close, a query, a refund or a payout, and a declaration has to
     * carry the capability before the service in front of the gateway sends anything.
     */
    private const CAPABILITIES = [
        PaymentCapability::CAPTURE,
        PaymentCapability::QUERY,
        PaymentCapability::CLOSE,
        PaymentCapability::REFUND,
        PaymentCapability::PARTIAL_REFUND,
        PaymentCapability::TRANSFER,
        PaymentCapability::TRANSFER_QUERY,
        PaymentCapability::MANDATE,
    ];

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
     * A repository that answers every search with the given entities, no matter what is searched for.
     *
     * @template TCollection of EntityCollection
     *
     * @param class-string<TCollection> $collectionClass only used to bind the collection to the repository
     * @param list<Entity> $entities
     *
     * @return EntityRepository<TCollection>
     */
    public static function repository(string $collectionClass, array $entities = []): EntityRepository
    {
        $repository = static::createStub(EntityRepository::class);
        $repository->method('search')->willReturn(self::searchResult($entities));

        return $repository;
    }

    public static function app(string $technicalName, string $secret, bool $active = true): PaymentAppEntity
    {
        $app = new PaymentAppEntity();
        $app->id = Uuid::randomHex();
        $app->setUniqueIdentifier($app->id);
        $app->technicalName = $technicalName;
        $app->name = $technicalName;
        $app->type = PaymentAppEntity::TYPE_EXTERNAL;
        $app->active = $active;
        $app->appSecret = self::cipher()->encryptValue($secret);

        return $app;
    }

    /**
     * @param array<string, mixed> $overrides
     */
    public static function order(string $paymentAppId, array $overrides = []): PaymentOrderEntity
    {
        $order = new PaymentOrderEntity();
        $order->id = Uuid::randomHex();
        $order->setUniqueIdentifier($order->id);
        $order->paymentNo = 'PG-1';
        $order->outTradeNo = 'T-1';
        $order->paymentAppId = $paymentAppId;
        $order->providerTechnicalName = 'alipay';
        $order->channelTechnicalName = 'alipay_h5';
        $order->amount = 1000;
        $order->currency = 'CNY';
        $order->subject = 'Order';
        $order->stateId = 'state-created';

        foreach ($overrides as $property => $value) {
            // @phpstan-ignore property.dynamicName (the overrides name the properties of the entity on purpose)
            $order->$property = $value;
        }

        return $order;
    }

    /**
     * @param array<string, mixed> $overrides
     */
    public static function refund(string $paymentOrderId, array $overrides = []): PaymentRefundEntity
    {
        $refund = new PaymentRefundEntity();
        $refund->id = Uuid::randomHex();
        $refund->setUniqueIdentifier($refund->id);
        $refund->refundNo = 'PR-1';
        $refund->paymentOrderId = $paymentOrderId;
        $refund->outRefundNo = 'R-1';
        $refund->amount = 500;
        $refund->currency = 'CNY';
        $refund->stateId = 'state-open';

        foreach ($overrides as $property => $value) {
            // @phpstan-ignore property.dynamicName (the overrides name the properties of the entity on purpose)
            $refund->$property = $value;
        }

        return $refund;
    }

    /**
     * The authorization of an app for the `alipay` interface.
     *
     * `sourceAppId` names the app whose merchant account is charged, `null` means "an account of the
     * app itself" - the credentials the app typed are then the ones of `credentials`.
     *
     * @param array<string, mixed>|null $credentials the merchant credentials of the app, sealed like
     *                                               the provisioning stores them
     */
    public static function appProvider(
        string $appId,
        bool $enabled = true,
        ?string $sourceAppId = null,
        ?array $credentials = null
    ): PaymentAppProviderEntity {
        $provider = new PaymentAppProviderEntity();
        $provider->id = Uuid::randomHex();
        $provider->setUniqueIdentifier($provider->id);
        $provider->paymentAppId = $appId;
        $provider->providerTechnicalName = 'alipay';
        $provider->enabled = $enabled;
        $provider->sourceAppId = $sourceAppId;
        $provider->credentials = $credentials === null ? null : self::cipher()->encrypt($credentials);

        return $provider;
    }

    public static function appChannel(string $appId, bool $enabled = true): PaymentAppChannelEntity
    {
        $channel = new PaymentAppChannelEntity();
        $channel->id = Uuid::randomHex();
        $channel->setUniqueIdentifier($channel->id);
        $channel->paymentAppId = $appId;
        $channel->channelTechnicalName = 'alipay_h5';
        $channel->enabled = $enabled;

        return $channel;
    }

    public static function channelConfig(bool $enabled = true): PaymentChannelConfigEntity
    {
        $config = new PaymentChannelConfigEntity();
        $config->id = Uuid::randomHex();
        $config->setUniqueIdentifier($config->id);
        $config->providerTechnicalName = 'alipay';
        $config->channelTechnicalName = 'alipay_h5';
        $config->enabled = $enabled;

        return $config;
    }

    public static function providerConfig(bool $enabled = true): PaymentProviderConfigEntity
    {
        $config = new PaymentProviderConfigEntity();
        $config->id = Uuid::randomHex();
        $config->setUniqueIdentifier($config->id);
        $config->providerTechnicalName = 'alipay';
        $config->enabled = $enabled;
        $config->credentials = self::cipher()->encrypt(['app_id' => 'platform-app']);

        return $config;
    }

    public static function channel(string $technicalName, string $providerTechnicalName): PaymentChannel
    {
        return new PaymentChannel($technicalName, $providerTechnicalName, $technicalName, PaymentFlow::REDIRECT);
    }

    /**
     * @param list<PaymentChannel> $channels
     * @param list<PaymentCapability> $capabilities what the interface can do - the services in front of
     *                                              the gateway ask the declaration before they send a
     *                                              request, so a fixture has to declare what its tests
     *                                              exercise
     */
    public static function provider(
        string $technicalName,
        array $channels = [],
        ?PaymentNotificationExtractorInterface $extractor = null,
        ?PaymentGatewayFactory $gatewayFactory = null,
        array $capabilities = self::CAPABILITIES
    ): PaymentProvider {
        $indexed = [];
        foreach ($channels as $channel) {
            $indexed[$channel->technicalName] = $channel;
        }

        return new PaymentProvider(
            $technicalName,
            $technicalName,
            [],
            $indexed,
            $gatewayFactory ?? static::createStub(PaymentGatewayFactory::class),
            $extractor ?? static::createStub(PaymentNotificationExtractorInterface::class),
            null,
            $capabilities,
        );
    }

    /**
     * A gateway factory whose gateway answers a close with the given answer - the channels themselves
     * are covered by their own tests, here only the path through the services matters.
     */
    public static function closingGatewayFactory(string $status = CloseResult::STATUS_CLOSED): PaymentGatewayFactory
    {
        $gateway = static::createStub(GatewayInterface::class);
        $gateway->method('execute')->willReturnCallback(static function (Generic $request) use ($status): void {
            $model = $request->getModel();

            if (!$model instanceof PaymentGatewayModel) {
                return;
            }

            $model->offsetSet(PaymentGatewayModel::REQUEST, ['out_trade_no' => 'T-1']);
            $model->offsetSet(PaymentGatewayModel::RESPONSE, ['status' => $status]);
            $model->offsetSet(PaymentGatewayModel::RESULT, new CloseResult($status, $status));
        });

        $factory = static::createStub(PaymentGatewayFactory::class);
        $factory->method('createForPayment')->willReturn($gateway);

        return $factory;
    }

    public static function providerRegistry(PaymentProvider ...$providers): PaymentProviderRegistry
    {
        return new PaymentProviderRegistry($providers);
    }

    public static function channelRegistry(PaymentProviderRegistry $providers): PaymentChannelRegistry
    {
        return new PaymentChannelRegistry($providers);
    }

    /**
     * @param list<PaymentAppProviderEntity> $appProviders
     * @param list<PaymentAppChannelEntity> $appChannels
     * @param list<PaymentChannelConfigEntity> $channelConfigs
     * @param list<PaymentProviderConfigEntity> $providerConfigs
     */
    public static function configResolver(
        PaymentProviderRegistry $providerRegistry,
        PaymentChannelRegistry $channelRegistry,
        array $appProviders = [],
        array $appChannels = [],
        array $channelConfigs = [],
        array $providerConfigs = []
    ): PaymentConfigResolver {
        return new PaymentConfigResolver(
            $providerRegistry,
            $channelRegistry,
            self::repository(PaymentAppProviderCollection::class, $appProviders),
            self::repository(PaymentProviderConfigCollection::class, $providerConfigs),
            self::repository(PaymentAppChannelCollection::class, $appChannels),
            self::repository(PaymentChannelConfigCollection::class, $channelConfigs),
            self::cipher(),
        );
    }

    /**
     * @param EntityRepository<PaymentOrderCollection> $paymentOrderRepository
     */
    public static function orderService(EntityRepository $paymentOrderRepository, PaymentConfigResolver $configResolver): PaymentOrderService
    {
        return new PaymentOrderService(
            $paymentOrderRepository,
            $configResolver,
            static::createStub(InitialStateIdLoader::class),
            new PaymentOrderStateHandler(static::createStub(StateMachineRegistry::class)),
            self::clock(),
        );
    }

    /**
     * @param EntityRepository<PaymentTransactionCollection> $transactionRepository
     */
    public static function captureService(EntityRepository $transactionRepository, PaymentConfigResolver $configResolver, PaymentOrderService $orderService): PaymentOrderCapture
    {
        return new PaymentOrderCapture(
            $transactionRepository,
            $configResolver,
            $orderService,
            new PaymentNotifyUrlBuilder(static::createStub(RequestStack::class)),
            self::clock(),
        );
    }

    /**
     * The close path end to end: the gateway answer, the transaction behind it and the bookkeeping of
     * the order - the reconciliation is part of it because a close may turn up a payment.
     *
     * @param EntityRepository<PaymentOrderCollection> $paymentOrderRepository
     * @param EntityRepository<PaymentTransactionCollection> $transactionRepository
     */
    public static function closer(
        EntityRepository $paymentOrderRepository,
        EntityRepository $transactionRepository,
        PaymentConfigResolver $configResolver,
        PaymentOrderService $orderService
    ): PaymentOrderCloser {
        $webhooks = self::webhookService();
        $paidHandler = new PaymentOrderPaidHandler($orderService, $webhooks, static::createStub(EventDispatcherInterface::class));

        return new PaymentOrderCloser(
            new PaymentCloser($transactionRepository, $configResolver, self::clock()),
            $orderService,
            new PaymentOrderReconciler(
                $paymentOrderRepository,
                self::repository(StateMachineStateCollection::class),
                $configResolver,
                $orderService,
                $paidHandler,
                new PaymentOrderQueryResultHandler($orderService, $webhooks, $paidHandler, self::clock()),
                self::clock(),
                static::createStub(LoggerInterface::class),
            ),
        );
    }

    /**
     * @param EntityRepository<PaymentRefundCollection> $refundRepository
     */
    public static function refundService(EntityRepository $refundRepository, PaymentConfigResolver $configResolver, PaymentOrderService $orderService): PaymentRefundService
    {
        return new PaymentRefundService(
            $refundRepository,
            $configResolver,
            $orderService,
            self::webhookService(),
            new PaymentNotifyUrlBuilder(static::createStub(RequestStack::class)),
            new PaymentRefundStateHandler(static::createStub(StateMachineRegistry::class)),
            static::createStub(InitialStateIdLoader::class),
            static::createStub(EventDispatcherInterface::class),
            self::clock(),
        );
    }

    public static function webhookService(): PaymentWebhookDispatcher
    {
        return new PaymentWebhookDispatcher(
            self::repository(PaymentWebhookDeliveryCollection::class),
            self::repository(PaymentAppCollection::class),
            new PaymentWebhookSigner(new PaymentSignatureService(), self::cipher(), self::clock()),
            static::createStub(HttpClientInterface::class),
            self::clock(),
            static::createStub(LoggerInterface::class),
        );
    }

    /**
     * @param EntityRepository<PaymentNotificationCollection> $notificationRepository
     */
    public static function notificationService(
        PaymentProviderRegistry $providerRegistry,
        EntityRepository $notificationRepository,
        PaymentOrderService $orderService,
        PaymentRefundService $refundService,
        PaymentConfigResolver $configResolver
    ): PaymentNotificationProcessor {
        return new PaymentNotificationProcessor(
            $notificationRepository,
            $providerRegistry,
            $orderService,
            $refundService,
            $configResolver,
            new PaymentOrderPaidHandler($orderService, self::webhookService(), static::createStub(EventDispatcherInterface::class)),
        );
    }

    public static function stateName(string $technicalName): StateMachineStateEntity
    {
        $state = new StateMachineStateEntity();
        $state->setId(Uuid::randomHex());
        $state->setStateMachineId(Uuid::randomHex());
        $state->setTechnicalName($technicalName);

        return $state;
    }

    /**
     * @param list<StateMachineStateEntity> $states
     */
    public static function presenter(array $states = []): PaymentOrderPresenter
    {
        return new PaymentOrderPresenter(self::repository(StateMachineStateCollection::class, $states));
    }

    /**
     * @param list<PaymentAppEntity> $apps
     */
    public static function authenticator(array $apps): PaymentAppAuthenticator
    {
        return new PaymentAppAuthenticator(
            self::repository(PaymentAppCollection::class, $apps),
            new PaymentSignatureService(),
            self::cipher(),
            self::cache(),
            self::clock(),
        );
    }

    /**
     * A request whose payload carries a valid signature of the given app.
     *
     * @param array<string, mixed> $params
     */
    public static function signedRequest(string $appId, string $secret, string $method, string $path, array $params = []): Request
    {
        $signatureService = new PaymentSignatureService();
        $timestamp = self::clock()->now()->getTimestamp();
        $nonce = 'nonce-' . $appId;

        $payload = array_merge([
            PaymentSignatureService::APP_ID => $appId,
            PaymentSignatureService::TIMESTAMP => (string) $timestamp,
            PaymentSignatureService::NONCE => $nonce,
        ], $params);

        $payload[PaymentSignatureService::SIGN] = $signatureService->sign(
            $secret,
            $appId,
            $timestamp,
            $nonce,
            $method,
            $path,
            $payload
        );

        if ($method === Request::METHOD_GET) {
            $request = new Request($payload, [], [], [], [], ['REQUEST_URI' => $path]);
            $request->setMethod(Request::METHOD_GET);

            return $request;
        }

        $request = new Request([], [], [], [], [], ['REQUEST_URI' => $path], (string) json_encode($payload));
        $request->setMethod($method);

        return $request;
    }

    /**
     * @param list<Entity> $entities
     *
     * @return EntitySearchResult<EntityCollection<Entity>>
     */
    private static function searchResult(array $entities): EntitySearchResult
    {
        return new EntitySearchResult(
            $entities === [] ? '' : $entities[0]->getApiAlias(),
            \count($entities),
            new EntityCollection($entities),
            null,
            new Criteria(),
            Context::createDefaultContext(),
        );
    }

    /**
     * A cache that holds nothing, so a nonce is never seen as replayed.
     */
    private static function cache(): CacheItemPoolInterface
    {
        $nonce = static::createStub(CacheItemInterface::class);
        $nonce->method('isHit')->willReturn(false);

        $cache = static::createStub(CacheItemPoolInterface::class);
        $cache->method('getItem')->willReturn($nonce);

        return $cache;
    }
}
