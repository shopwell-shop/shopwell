<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Payment\Storefront;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Payment\PaymentException;
use Shopwell\Core\Checkout\Payment\Storefront\PlatformPaymentApp;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppChannel\PaymentAppChannelCollection;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppChannel\PaymentAppChannelEntity;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppProvider\PaymentAppProviderCollection;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppProvider\PaymentAppProviderEntity;
use Shopwell\Core\System\Payment\App\PaymentAppCollection;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayFactory;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationExtractorInterface;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentChannel;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentChannelRegistry;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentFlow;
use Shopwell\Core\System\Payment\Provider\PaymentChannelConfigCollection;
use Shopwell\Core\System\Payment\Provider\PaymentChannelConfigEntity;
use Shopwell\Core\System\Payment\Provider\PaymentProvider;
use Shopwell\Core\System\Payment\Provider\PaymentProviderConfigCollection;
use Shopwell\Core\System\Payment\Provider\PaymentProviderConfigEntity;
use Shopwell\Core\System\Payment\Provider\PaymentProviderRegistry;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * The tenant of the shop is written by the payment migration and by nothing else, and what it may
 * collect through is added for the channel that is really used - on the merchant account of the
 * platform.
 *
 * @internal
 */
#[Package('checkout')]
#[CoversClass(PlatformPaymentApp::class)]
class PlatformPaymentAppTest extends TestCase
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
                ],
                gatewayFactory: static::createStub(PaymentGatewayFactory::class),
                notificationExtractor: static::createStub(PaymentNotificationExtractorInterface::class),
            ),
        ]);
        $this->channels = new PaymentChannelRegistry($this->providers);
    }

    public function testTheSeededTenantIsHandedOutAsItIs(): void
    {
        $appRepository = $this->appRepository([$this->platformApp()]);
        $providerRepository = $this->providerRepository([$this->enabledProvider()]);
        $channelRepository = $this->channelRepository([$this->offeredChannel()]);

        $app = $this->platformAppService($appRepository, $providerRepository, $channelRepository)
            ->appForChannel('alipay_h5');

        static::assertSame(self::APP_ID, $app->id);
        static::assertSame(PaymentAppEntity::TYPE_PLATFORM, $app->type);

        // an authorization that already exists is respected and never rewritten
        static::assertSame([], $providerRepository->upserts);
        static::assertSame([], $channelRepository->creates);
    }

    /**
     * The tenant is seeded by the migration. Writing it here as well would leave two writers of the
     * same row and make the type of a tenant impossible to reason about - a missing row is reported
     * instead, and the payment is never collected under an app nobody seeded.
     */
    public function testTheTenantIsNeverCreatedAtRuntime(): void
    {
        $appRepository = $this->appRepository([]);

        try {
            $this->platformAppService($appRepository)->appForChannel('alipay_h5');
            static::fail('a missing platform tenant must not be replaced by a new app');
        } catch (PaymentException $exception) {
            static::assertSame(PaymentException::PAYMENT_PLATFORM_TENANT_MISSING, $exception->getErrorCode());
        }

        static::assertSame([], $appRepository->creates);
        static::assertSame([], $appRepository->upserts);
    }

    public function testAUsedChannelIsAuthorizedOnThePlatformAccountAndOffered(): void
    {
        $providerRepository = $this->providerRepository([]);
        $channelRepository = $this->channelRepository([]);

        $this->platformAppService($this->appRepository([$this->platformApp()]), $providerRepository, $channelRepository)
            ->appForChannel('alipay_h5');

        static::assertCount(1, $providerRepository->upserts);
        static::assertNull($providerRepository->upserts[0][0]['sourceAppId']);
        static::assertTrue($providerRepository->upserts[0][0]['enabled']);

        static::assertCount(1, $channelRepository->creates);
        static::assertSame('alipay_h5', $channelRepository->creates[0][0]['channelTechnicalName']);
        static::assertTrue($channelRepository->creates[0][0]['enabled']);
    }

    public function testAChannelThePlatformDidNotEnableIsNotHandedOut(): void
    {
        $service = $this->platformAppService(
            $this->appRepository([$this->platformApp()]),
            channelConfigs: $this->channelConfigs([])
        );

        $this->expectExceptionObject(PaymentException::channelDisabledByPlatform('alipay_h5'));

        $service->appForChannel('alipay_h5');
    }

    public function testAnInterfaceWithoutCredentialsOffersNoChannel(): void
    {
        $channels = $this->platformAppService(
            $this->appRepository([$this->platformApp()]),
            providerConfigs: $this->providerConfigs([])
        )->availableChannels('CNY', PaymentChannel::DEVICE_WEB);

        static::assertSame([], $channels);
    }

    public function testTheAvailableChannelsAreTheOnesThatCanReallyBeCollected(): void
    {
        $channels = $this->platformAppService($this->appRepository([$this->platformApp()]))
            ->availableChannels('CNY', PaymentChannel::DEVICE_WEB);

        static::assertCount(1, $channels);
        static::assertSame('alipay_h5', $channels[0]['channel']);
        static::assertSame('支付宝', $channels[0]['provider_label']);
        static::assertSame(PaymentFlow::REDIRECT->value, $channels[0]['flow']);
    }

    /**
     * @param StaticEntityRepository<PaymentAppCollection> $appRepository
     * @param StaticEntityRepository<PaymentAppProviderCollection>|null $providerRepository
     * @param StaticEntityRepository<PaymentAppChannelCollection>|null $channelRepository
     * @param StaticEntityRepository<PaymentChannelConfigCollection>|null $channelConfigs
     * @param StaticEntityRepository<PaymentProviderConfigCollection>|null $providerConfigs
     */
    private function platformAppService(
        StaticEntityRepository $appRepository,
        ?StaticEntityRepository $providerRepository = null,
        ?StaticEntityRepository $channelRepository = null,
        ?StaticEntityRepository $channelConfigs = null,
        ?StaticEntityRepository $providerConfigs = null,
    ): PlatformPaymentApp {
        return new PlatformPaymentApp(
            $appRepository,
            $providerRepository ?? $this->providerRepository([]),
            $channelRepository ?? $this->channelRepository([]),
            $providerConfigs ?? $this->providerConfigs(['alipay']),
            $channelConfigs ?? $this->channelConfigs(['alipay_h5']),
            $this->channels,
            $this->providers,
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
     * @param list<PaymentAppProviderEntity> $providers
     *
     * @return StaticEntityRepository<PaymentAppProviderCollection>
     */
    private function providerRepository(array $providers): StaticEntityRepository
    {
        return StaticEntityRepository::of(PaymentAppProviderCollection::class, [new PaymentAppProviderCollection($providers)]);
    }

    /**
     * @param list<PaymentAppChannelEntity> $channels
     *
     * @return StaticEntityRepository<PaymentAppChannelCollection>
     */
    private function channelRepository(array $channels): StaticEntityRepository
    {
        return StaticEntityRepository::of(PaymentAppChannelCollection::class, [new PaymentAppChannelCollection($channels)]);
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

    /**
     * @param list<string> $providers
     *
     * @return StaticEntityRepository<PaymentProviderConfigCollection>
     */
    private function providerConfigs(array $providers): StaticEntityRepository
    {
        $configs = [];

        foreach ($providers as $provider) {
            $config = new PaymentProviderConfigEntity();
            $config->id = md5('provider-config-' . $provider);
            $config->providerTechnicalName = $provider;
            $config->enabled = true;
            $config->credentials = ['app_id' => 'platform-app-id'];
            $config->setUniqueIdentifier($config->id);

            $configs[] = $config;
        }

        return StaticEntityRepository::of(PaymentProviderConfigCollection::class, [new PaymentProviderConfigCollection($configs)]);
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

    private function enabledProvider(): PaymentAppProviderEntity
    {
        $provider = new PaymentAppProviderEntity();
        $provider->id = 'app-provider-id';
        $provider->paymentAppId = self::APP_ID;
        $provider->providerTechnicalName = 'alipay';
        $provider->enabled = true;
        $provider->sourceAppId = null;
        $provider->setUniqueIdentifier($provider->id);

        return $provider;
    }

    private function offeredChannel(): PaymentAppChannelEntity
    {
        $channel = new PaymentAppChannelEntity();
        $channel->id = 'app-channel-id';
        $channel->paymentAppId = self::APP_ID;
        $channel->channelTechnicalName = 'alipay_h5';
        $channel->enabled = true;
        $channel->setUniqueIdentifier($channel->id);

        return $channel;
    }
}
