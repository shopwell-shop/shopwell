<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Controller;

use Payum\Core\GatewayFactoryInterface;
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
use Shopwell\Core\System\Payment\Channel\PaymentChannel;
use Shopwell\Core\System\Payment\Channel\PaymentChannelRegistry;
use Shopwell\Core\System\Payment\Channel\PaymentFlow;
use Shopwell\Core\System\Payment\DataAbstractionLayer\PaymentAppChannelCollection;
use Shopwell\Core\System\Payment\DataAbstractionLayer\PaymentAppChannelEntity;
use Shopwell\Core\System\Payment\DataAbstractionLayer\PaymentAppCollection;
use Shopwell\Core\System\Payment\DataAbstractionLayer\PaymentAppEntity;
use Shopwell\Core\System\Payment\DataAbstractionLayer\PaymentAppProviderCollection;
use Shopwell\Core\System\Payment\DataAbstractionLayer\PaymentAppProviderEntity;
use Shopwell\Core\System\Payment\DataAbstractionLayer\PaymentChannelConfigCollection;
use Shopwell\Core\System\Payment\DataAbstractionLayer\PaymentChannelConfigEntity;
use Shopwell\Core\System\Payment\DataAbstractionLayer\PaymentNotificationCollection;
use Shopwell\Core\System\Payment\DataAbstractionLayer\PaymentOrderCollection;
use Shopwell\Core\System\Payment\DataAbstractionLayer\PaymentOrderEntity;
use Shopwell\Core\System\Payment\DataAbstractionLayer\PaymentProviderConfigCollection;
use Shopwell\Core\System\Payment\DataAbstractionLayer\PaymentProviderConfigEntity;
use Shopwell\Core\System\Payment\DataAbstractionLayer\PaymentRefundCollection;
use Shopwell\Core\System\Payment\DataAbstractionLayer\PaymentRefundEntity;
use Shopwell\Core\System\Payment\DataAbstractionLayer\PaymentTransactionCollection;
use Shopwell\Core\System\Payment\DataAbstractionLayer\PaymentWebhookDeliveryCollection;
use Shopwell\Core\System\Payment\Provider\PaymentNotificationExtractorInterface;
use Shopwell\Core\System\Payment\Provider\PaymentProvider;
use Shopwell\Core\System\Payment\Provider\PaymentProviderRegistry;
use Shopwell\Core\System\Payment\Service\PaymentAppAuthenticator;
use Shopwell\Core\System\Payment\Service\PaymentCaptureService;
use Shopwell\Core\System\Payment\Service\PaymentConfigResolver;
use Shopwell\Core\System\Payment\Service\PaymentCredentialCipher;
use Shopwell\Core\System\Payment\Service\PaymentGatewayFactory;
use Shopwell\Core\System\Payment\Service\PaymentNotificationService;
use Shopwell\Core\System\Payment\Service\PaymentNotifyUrlBuilder;
use Shopwell\Core\System\Payment\Service\PaymentOrderPresenter;
use Shopwell\Core\System\Payment\Service\PaymentOrderService;
use Shopwell\Core\System\Payment\Service\PaymentRefundService;
use Shopwell\Core\System\Payment\Service\PaymentSignatureService;
use Shopwell\Core\System\Payment\Service\PaymentWebhookService;
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

    public static function appProvider(string $appId, bool $enabled = true, string $mode = PaymentAppProviderEntity::MODE_PLATFORM): PaymentAppProviderEntity
    {
        $provider = new PaymentAppProviderEntity();
        $provider->id = Uuid::randomHex();
        $provider->setUniqueIdentifier($provider->id);
        $provider->paymentAppId = $appId;
        $provider->providerTechnicalName = 'alipay';
        $provider->enabled = $enabled;
        $provider->mode = $mode;

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

    public static function channel(string $technicalName, string $providerName): PaymentChannel
    {
        return new PaymentChannel($technicalName, $providerName, $technicalName, PaymentFlow::REDIRECT);
    }

    /**
     * @param list<PaymentChannel> $channels
     */
    public static function provider(string $technicalName, array $channels = [], ?PaymentNotificationExtractorInterface $extractor = null): PaymentProvider
    {
        $indexed = [];
        foreach ($channels as $channel) {
            $indexed[$channel->technicalName] = $channel;
        }

        return new PaymentProvider(
            $technicalName,
            $technicalName,
            [],
            $indexed,
            static::createStub(GatewayFactoryInterface::class),
            $extractor ?? static::createStub(PaymentNotificationExtractorInterface::class),
        );
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
            static::createStub(StateMachineRegistry::class),
            self::clock(),
        );
    }

    /**
     * @param EntityRepository<PaymentTransactionCollection> $transactionRepository
     */
    public static function captureService(EntityRepository $transactionRepository, PaymentConfigResolver $configResolver, PaymentOrderService $orderService): PaymentCaptureService
    {
        return new PaymentCaptureService(
            $transactionRepository,
            new PaymentGatewayFactory(),
            $configResolver,
            $orderService,
            new PaymentNotifyUrlBuilder(static::createStub(RequestStack::class)),
            self::clock(),
        );
    }

    /**
     * @param EntityRepository<PaymentRefundCollection> $refundRepository
     */
    public static function refundService(EntityRepository $refundRepository, PaymentConfigResolver $configResolver, PaymentOrderService $orderService): PaymentRefundService
    {
        return new PaymentRefundService(
            $refundRepository,
            new PaymentGatewayFactory(),
            $configResolver,
            $orderService,
            self::webhookService(),
            new PaymentNotifyUrlBuilder(static::createStub(RequestStack::class)),
            static::createStub(StateMachineRegistry::class),
            static::createStub(InitialStateIdLoader::class),
            static::createStub(EventDispatcherInterface::class),
            self::clock(),
        );
    }

    public static function webhookService(): PaymentWebhookService
    {
        return new PaymentWebhookService(
            self::repository(PaymentWebhookDeliveryCollection::class),
            self::repository(PaymentAppCollection::class),
            new PaymentSignatureService(),
            self::cipher(),
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
    ): PaymentNotificationService {
        return new PaymentNotificationService(
            $notificationRepository,
            $providerRegistry,
            $orderService,
            $refundService,
            $configResolver,
            new PaymentGatewayFactory(),
            self::webhookService(),
            static::createStub(EventDispatcherInterface::class),
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
