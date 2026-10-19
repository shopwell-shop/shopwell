<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Payment\Storefront;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Payment\PaymentException;
use Shopwell\Core\Checkout\Payment\Storefront\PlatformPaymentApp;
use Shopwell\Core\Checkout\Payment\Storefront\StorefrontPaymentService;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppChannel\PaymentAppChannelCollection;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppProvider\PaymentAppProviderCollection;
use Shopwell\Core\System\Payment\App\PaymentAppCollection;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\App\PaymentCredentialCipher;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayFactory;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationExtractorInterface;
use Shopwell\Core\System\Payment\Notification\PaymentNotifyUrlBuilder;
use Shopwell\Core\System\Payment\Order\PaymentOrderCapture;
use Shopwell\Core\System\Payment\Order\PaymentOrderService;
use Shopwell\Core\System\Payment\Order\PaymentOrderStateHandler;
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
use Shopwell\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Shopwell\Core\System\StateMachine\StateMachineRegistry;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * The storefront only offers what the platform app can really collect through, and it opens the
 * payment through the app of the channel - never through an app it creates itself.
 *
 * @internal
 */
#[Package('checkout')]
#[CoversClass(StorefrontPaymentService::class)]
class StorefrontPaymentServiceTest extends TestCase
{
    private const APP_ID = 'a1b2c3d4e5f60718293a4b5c6d7e8f90';

    private PaymentChannelRegistry $channels;

    private PaymentProviderRegistry $providers;

    protected function setUp(): void
    {
        $this->providers = new PaymentProviderRegistry([
            new PaymentProvider(
                technicalName: 'alipay',
                label: '支付宝',
                credentials: [],
                channels: [
                    'alipay_h5' => new PaymentChannel(
                        technicalName: 'alipay_h5',
                        providerTechnicalName: 'alipay',
                        label: '支付宝手机网站',
                        flow: PaymentFlow::REDIRECT,
                        currencies: ['CNY'],
                        devices: [PaymentChannel::DEVICE_WAP, PaymentChannel::DEVICE_WEB],
                    ),
                    'alipay_global' => new PaymentChannel(
                        technicalName: 'alipay_global',
                        providerTechnicalName: 'alipay',
                        label: '支付宝境外',
                        flow: PaymentFlow::REDIRECT,
                        currencies: ['USD'],
                    ),
                ],
                gatewayFactory: static::createStub(PaymentGatewayFactory::class),
                notificationExtractor: static::createStub(PaymentNotificationExtractorInterface::class),
            ),
        ]);
        $this->channels = new PaymentChannelRegistry($this->providers);
    }

    public function testTheOfferedChannelsAreTheOnesThatCanReallyBeCollected(): void
    {
        $service = $this->service($this->appRepository([$this->platformApp()]));

        $channels = $service->channels('CNY', PaymentChannel::DEVICE_WEB);

        static::assertCount(1, $channels);
        static::assertSame('alipay_h5', $channels[0]['channel']);
        static::assertSame('支付宝', $channels[0]['provider_label']);
        static::assertSame(PaymentFlow::REDIRECT->value, $channels[0]['flow']);
    }

    public function testAChannelOfAnotherCurrencyIsNotOffered(): void
    {
        $channels = $this->service(
            $this->appRepository([$this->platformApp()]),
            $this->channelConfigs(['alipay_h5', 'alipay_global'])
        )->channels('USD', PaymentChannel::DEVICE_WEB);

        static::assertCount(1, $channels);
        static::assertSame('alipay_global', $channels[0]['channel']);
    }

    public function testAnInterfaceWithoutCredentialsOffersNoChannel(): void
    {
        $channels = $this->service(
            $this->appRepository([$this->platformApp()]),
            providerConfigRepository: $this->providerConfigs(['alipay' => []])
        )->channels('CNY', PaymentChannel::DEVICE_WEB);

        static::assertSame([], $channels);
    }

    public function testOpeningAPaymentWithoutASeededTenantFails(): void
    {
        $service = $this->service($this->appRepository([]));

        $this->expectExceptionObject(PaymentException::platformTenantMissing(PaymentAppEntity::PLATFORM_TECHNICAL_NAME));

        $service->open('out-trade-no', 'alipay_h5', 100, 'CNY', 'Wallet top-up', 'wallet_top_up:1');
    }

    public function testOpeningAChannelThePlatformDidNotEnableFails(): void
    {
        $service = $this->service(
            $this->appRepository([$this->platformApp()]),
            $this->channelConfigs([])
        );

        $this->expectExceptionObject(PaymentException::channelDisabledByPlatform('alipay_h5'));

        $service->open('out-trade-no', 'alipay_h5', 100, 'CNY', 'Wallet top-up', 'wallet_top_up:1');
    }

    /**
     * @param StaticEntityRepository<PaymentAppCollection> $appRepository
     * @param StaticEntityRepository<PaymentChannelConfigCollection>|null $channelConfigRepository
     * @param StaticEntityRepository<PaymentProviderConfigCollection>|null $providerConfigRepository
     */
    private function service(
        StaticEntityRepository $appRepository,
        ?StaticEntityRepository $channelConfigRepository = null,
        ?StaticEntityRepository $providerConfigRepository = null,
    ): StorefrontPaymentService {
        $configResolver = new PaymentConfigResolver(
            $this->providers,
            $this->channels,
            static::createStub(EntityRepository::class),
            static::createStub(EntityRepository::class),
            static::createStub(EntityRepository::class),
            static::createStub(EntityRepository::class),
            new PaymentCredentialCipher('test-secret'),
        );

        $orderService = new PaymentOrderService(
            static::createStub(EntityRepository::class),
            $configResolver,
            static::createStub(InitialStateIdLoader::class),
            new PaymentOrderStateHandler(static::createStub(StateMachineRegistry::class)),
            new MockClock('2026-06-01 12:00:00'),
        );

        return new StorefrontPaymentService(
            new PlatformPaymentApp(
                $appRepository,
                StaticEntityRepository::of(PaymentAppProviderCollection::class, [new PaymentAppProviderCollection()]),
                StaticEntityRepository::of(PaymentAppChannelCollection::class, [new PaymentAppChannelCollection()]),
                $providerConfigRepository ?? $this->providerConfigs(['alipay' => ['app_id' => 'platform-app-id']]),
                $channelConfigRepository ?? $this->channelConfigs(['alipay_h5']),
                $this->channels,
                $this->providers,
            ),
            $orderService,
            new PaymentOrderCapture(
                static::createStub(EntityRepository::class),
                $configResolver,
                $orderService,
                new PaymentNotifyUrlBuilder(new RequestStack()),
                new MockClock('2026-06-01 12:00:00'),
            ),
        );
    }

    /**
     * @param list<PaymentAppEntity> $apps
     *
     * @return StaticEntityRepository<PaymentAppCollection>
     */
    private function appRepository(array $apps): StaticEntityRepository
    {
        return StaticEntityRepository::of(PaymentAppCollection::class, [new PaymentAppCollection($apps)]);
    }

    /**
     * @param array<string, array<string, string>> $credentials
     *
     * @return StaticEntityRepository<PaymentProviderConfigCollection>
     */
    private function providerConfigs(array $credentials): StaticEntityRepository
    {
        $configs = [];

        foreach ($credentials as $provider => $values) {
            $config = new PaymentProviderConfigEntity();
            $config->id = md5('provider-config-' . $provider);
            $config->providerTechnicalName = $provider;
            $config->enabled = true;
            $config->credentials = $values;
            $config->setUniqueIdentifier($config->id);

            $configs[] = $config;
        }

        return StaticEntityRepository::of(PaymentProviderConfigCollection::class, [new PaymentProviderConfigCollection($configs)]);
    }

    /**
     * @param list<string> $channels
     *
     * @return StaticEntityRepository<PaymentChannelConfigCollection>
     */
    private function channelConfigs(array $channels): StaticEntityRepository
    {
        $configs = [];

        foreach ($channels as $channel) {
            $config = new PaymentChannelConfigEntity();
            $config->id = md5('channel-config-' . $channel);
            $config->providerTechnicalName = 'alipay';
            $config->channelTechnicalName = $channel;
            $config->enabled = true;
            $config->setUniqueIdentifier($config->id);

            $configs[] = $config;
        }

        return StaticEntityRepository::of(PaymentChannelConfigCollection::class, [new PaymentChannelConfigCollection($configs)]);
    }

    private function platformApp(): PaymentAppEntity
    {
        $app = new PaymentAppEntity();
        $app->id = self::APP_ID;
        $app->technicalName = PaymentAppEntity::PLATFORM_TECHNICAL_NAME;
        $app->type = PaymentAppEntity::TYPE_PLATFORM;
        $app->setUniqueIdentifier($app->id);

        return $app;
    }
}
