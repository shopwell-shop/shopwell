<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Provider;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppChannel\PaymentAppChannelCollection;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppChannel\PaymentAppChannelEntity;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppProvider\PaymentAppProviderCollection;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppProvider\PaymentAppProviderEntity;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\App\PaymentCredentialCipher;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayFactory;
use Shopwell\Core\System\Payment\PaymentException;
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
use Shopwell\Core\System\Payment\Provider\Stripe\StripeApi;
use Shopwell\Core\System\Payment\Provider\Stripe\StripeNotificationExtractor;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentConfigResolver::class)]
class PaymentConfigResolverTest extends TestCase
{
    private const PROVIDER = 'stripe';
    private const CHANNEL = 'stripe_checkout';

    private PaymentCredentialCipher $cipher;

    private PaymentAppEntity $app;

    protected function setUp(): void
    {
        $this->cipher = new PaymentCredentialCipher('application-secret');

        $app = new PaymentAppEntity();
        $app->id = 'app-1';
        $app->technicalName = 'app';
        $this->app = $app;
    }

    public function testResolveRejectsAnUnknownChannel(): void
    {
        $resolver = $this->resolver();

        $this->expectExceptionObject(PaymentException::unknownChannel('unknown_channel'));

        $resolver->resolve($this->app, 'unknown_channel', 'CNY');
    }

    public function testResolveRejectsAnUnsupportedCurrency(): void
    {
        $resolver = $this->resolver($this->channel(currencies: ['CNY']));

        $this->expectExceptionObject(PaymentException::currencyNotSupported(self::CHANNEL, 'USD'));

        $resolver->resolve($this->app, self::CHANNEL, 'USD');
    }

    public function testResolveRejectsAnUnsupportedDevice(): void
    {
        $resolver = $this->resolver($this->channel(devices: ['web']));

        $this->expectExceptionObject(PaymentException::deviceNotSupported(self::CHANNEL, 'app'));

        $resolver->resolve($this->app, self::CHANNEL, 'CNY', 'app');
    }

    public function testResolveRejectsAMissingRuntimeParameter(): void
    {
        $resolver = $this->resolver($this->channel(runtimeParams: ['openid' => true, 'optional' => false]));

        $this->expectExceptionObject(PaymentException::missingRuntimeParams(self::CHANNEL, ['openid']));

        $resolver->resolve($this->app, self::CHANNEL, 'CNY');
    }

    public function testResolveRejectsAProviderTheAppIsNotAuthorizedFor(): void
    {
        $resolver = $this->resolver(appProviders: $this->appProviders());

        $this->expectExceptionObject(PaymentException::providerNotEnabled(self::PROVIDER, 'app'));

        $resolver->resolve($this->app, self::CHANNEL, 'CNY');
    }

    public function testResolveRejectsADisabledProviderAuthorization(): void
    {
        $resolver = $this->resolver(appProviders: $this->appProviders($this->appProvider(enabled: false)));

        $this->expectExceptionObject(PaymentException::providerNotEnabled(self::PROVIDER, 'app'));

        $resolver->resolve($this->app, self::CHANNEL, 'CNY');
    }

    public function testResolveRejectsAChannelTheAppHasNotEnabled(): void
    {
        $resolver = $this->resolver(
            appProviders: $this->appProviders($this->appProvider())
        );

        $this->expectExceptionObject(PaymentException::channelNotEnabled(self::CHANNEL, 'app'));

        $resolver->resolve($this->app, self::CHANNEL, 'CNY');
    }

    public function testResolveRejectsADisabledAppChannel(): void
    {
        $resolver = $this->resolver(
            appProviders: $this->appProviders($this->appProvider()),
            appChannels: $this->appChannels($this->appChannel(enabled: false))
        );

        $this->expectExceptionObject(PaymentException::channelNotEnabled(self::CHANNEL, 'app'));

        $resolver->resolve($this->app, self::CHANNEL, 'CNY');
    }

    public function testResolveRejectsAChannelThePlatformDoesNotOffer(): void
    {
        $resolver = $this->resolver(
            appProviders: $this->appProviders($this->appProvider()),
            appChannels: $this->appChannels($this->appChannel())
        );

        $this->expectExceptionObject(PaymentException::channelDisabled(self::CHANNEL));

        $resolver->resolve($this->app, self::CHANNEL, 'CNY');
    }

    public function testResolveRejectsADisabledPlatformChannel(): void
    {
        $resolver = $this->resolver(
            appProviders: $this->appProviders($this->appProvider()),
            appChannels: $this->appChannels($this->appChannel()),
            channelConfigs: $this->channelConfigs($this->channelConfig(enabled: false))
        );

        $this->expectExceptionObject(PaymentException::channelDisabled(self::CHANNEL));

        $resolver->resolve($this->app, self::CHANNEL, 'CNY');
    }

    public function testResolveRejectsAMissingPlatformConfiguration(): void
    {
        $resolver = $this->resolver(
            appProviders: $this->appProviders($this->appProvider()),
            appChannels: $this->appChannels($this->appChannel()),
            channelConfigs: $this->channelConfigs($this->channelConfig())
        );

        $this->expectExceptionObject(PaymentException::providerNotConfigured(self::PROVIDER));

        $resolver->resolve($this->app, self::CHANNEL, 'CNY');
    }

    public function testResolveRejectsAnEmptyPlatformCredential(): void
    {
        $resolver = $this->resolver(
            appProviders: $this->appProviders($this->appProvider()),
            appChannels: $this->appChannels($this->appChannel()),
            channelConfigs: $this->channelConfigs($this->channelConfig()),
            providerConfigs: $this->providerConfigs($this->providerConfig(credentials: null))
        );

        $this->expectExceptionObject(PaymentException::providerNotConfigured(self::PROVIDER));

        $resolver->resolve($this->app, self::CHANNEL, 'CNY');
    }

    public function testResolveRejectsEmptyOwnCredentials(): void
    {
        $resolver = $this->resolver(
            appProviders: $this->appProviders($this->appProvider(mode: PaymentAppProviderEntity::MODE_OWN, credentials: null)),
            appChannels: $this->appChannels($this->appChannel()),
            channelConfigs: $this->channelConfigs($this->channelConfig())
        );

        $this->expectExceptionObject(PaymentException::providerNotConfigured(self::PROVIDER));

        $resolver->resolve($this->app, self::CHANNEL, 'CNY');
    }

    public function testResolveUsesThePlatformCredentialsAndSettings(): void
    {
        $resolver = $this->resolver(
            channel: $this->channel(runtimeParams: ['openid' => true]),
            appProviders: $this->appProviders($this->appProvider(settings: ['from_app_provider' => 'app'])),
            appChannels: $this->appChannels($this->appChannel(settings: ['from_app_channel' => 'app'])),
            channelConfigs: $this->channelConfigs($this->channelConfig(
                params: $this->cipher->encrypt(['appid' => 'wx-platform']),
                settings: ['from_channel_config' => 'platform']
            )),
            providerConfigs: $this->providerConfigs($this->providerConfig(
                credentials: $this->cipher->encrypt(['secret_key' => 'sk_platform'])
            ))
        );

        $resolved = $resolver->resolve($this->app, self::CHANNEL, 'CNY', 'web', ['openid' => 'o-1']);

        static::assertSame(self::PROVIDER, $resolved->provider->technicalName);
        static::assertSame(self::CHANNEL, $resolved->channel->technicalName);
        static::assertSame(['secret_key' => 'sk_platform'], $resolved->credentials);
        static::assertSame(['appid' => 'wx-platform'], $resolved->params);
        static::assertSame(['openid' => 'o-1'], $resolved->runtimeParams);
        static::assertSame(PaymentAppProviderEntity::MODE_PLATFORM, $resolved->mode);
        static::assertSame(
            ['from_channel_config' => 'platform', 'from_app_provider' => 'app', 'from_app_channel' => 'app'],
            $resolved->settings
        );
        static::assertSame('sk_platform', $resolved->require('secret_key'));
        static::assertSame('wx-platform', $resolved->requireParam('appid'));
        static::assertSame('app', $resolved->setting('from_app_provider'));
        static::assertSame('o-1', $resolved->runtimeParam('openid'));
    }

    public function testResolveUsesTheOwnCredentialsInOwnMode(): void
    {
        $resolver = $this->resolver(
            appProviders: $this->appProviders($this->appProvider(
                mode: PaymentAppProviderEntity::MODE_OWN,
                credentials: $this->cipher->encrypt(['secret_key' => 'sk_own'])
            )),
            appChannels: $this->appChannels($this->appChannel(
                params: $this->cipher->encrypt(['appid' => 'wx-own'])
            )),
            channelConfigs: $this->channelConfigs($this->channelConfig())
        );

        $resolved = $resolver->resolve($this->app, self::CHANNEL, 'CNY');

        static::assertSame(PaymentAppProviderEntity::MODE_OWN, $resolved->mode);
        static::assertSame(['secret_key' => 'sk_own'], $resolved->credentials);
        static::assertSame(['appid' => 'wx-own'], $resolved->params);
    }

    public function testEnabledForReturnsNothingWithoutAnyAuthorization(): void
    {
        static::assertSame([], $this->resolver()->enabledFor($this->app));
    }

    public function testEnabledForListsTheChannelsOfTheAuthorizedProviders(): void
    {
        $resolver = $this->resolver(
            channel: $this->channel(runtimeParams: ['openid' => true]),
            appProviders: $this->appProviders($this->appProvider(mode: PaymentAppProviderEntity::MODE_OWN)),
            appChannels: $this->appChannels($this->appChannel())
        );

        static::assertSame([
            [
                'channel' => self::CHANNEL,
                'channel_label' => 'Stripe Checkout',
                'provider' => self::PROVIDER,
                'provider_label' => 'Stripe',
                'flow' => PaymentFlow::REDIRECT->value,
                'currencies' => ['CNY'],
                'devices' => ['web'],
                'runtime_params' => ['openid' => true],
                'mode' => PaymentAppProviderEntity::MODE_OWN,
            ],
        ], $resolver->enabledFor($this->app));
    }

    public function testEnabledForSkipsAChannelThatIsNoLongerInstalled(): void
    {
        $resolver = $this->resolver(
            appProviders: $this->appProviders($this->appProvider()),
            appChannels: $this->appChannels($this->appChannel(channelTechnicalName: 'removed_channel'))
        );

        static::assertSame([], $resolver->enabledFor($this->app));
    }

    public function testEnabledForSkipsAChannelWhoseProviderIsNotAuthorized(): void
    {
        $resolver = $this->resolver(
            appProviders: $this->appProviders($this->appProvider(providerTechnicalName: 'paypal')),
            appChannels: $this->appChannels($this->appChannel())
        );

        static::assertSame([], $resolver->enabledFor($this->app));
    }

    /**
     * @param list<string> $currencies
     * @param list<string> $devices
     * @param array<string, bool> $runtimeParams
     */
    private function channel(
        array $currencies = ['CNY'],
        array $devices = ['web'],
        array $runtimeParams = []
    ): PaymentChannel {
        return new PaymentChannel(
            self::CHANNEL,
            self::PROVIDER,
            'Stripe Checkout',
            PaymentFlow::REDIRECT,
            $currencies,
            $devices,
            [],
            $runtimeParams
        );
    }

    /**
     * @param array<string, mixed>|null $credentials
     * @param array<string, mixed>|null $settings
     */
    private function appProvider(
        bool $enabled = true,
        string $mode = PaymentAppProviderEntity::MODE_PLATFORM,
        ?array $credentials = null,
        ?array $settings = null,
        string $providerTechnicalName = self::PROVIDER
    ): PaymentAppProviderEntity {
        $entity = new PaymentAppProviderEntity();
        $entity->setUniqueIdentifier('app-provider-1');
        $entity->paymentAppId = 'app-1';
        $entity->providerTechnicalName = $providerTechnicalName;
        $entity->enabled = $enabled;
        $entity->mode = $mode;
        $entity->credentials = $credentials;
        $entity->settings = $settings;

        return $entity;
    }

    /**
     * @param array<string, mixed>|null $params
     * @param array<string, mixed>|null $settings
     */
    private function appChannel(
        bool $enabled = true,
        ?array $params = null,
        ?array $settings = null,
        string $channelTechnicalName = self::CHANNEL
    ): PaymentAppChannelEntity {
        $entity = new PaymentAppChannelEntity();
        $entity->setUniqueIdentifier('app-channel-1');
        $entity->paymentAppId = 'app-1';
        $entity->channelTechnicalName = $channelTechnicalName;
        $entity->enabled = $enabled;
        $entity->params = $params;
        $entity->settings = $settings;

        return $entity;
    }

    /**
     * @param array<string, mixed>|null $credentials
     * @param array<string, mixed>|null $settings
     */
    private function providerConfig(
        bool $enabled = true,
        ?array $credentials = null,
        ?array $settings = null
    ): PaymentProviderConfigEntity {
        $entity = new PaymentProviderConfigEntity();
        $entity->setUniqueIdentifier('provider-config-1');
        $entity->providerTechnicalName = self::PROVIDER;
        $entity->enabled = $enabled;
        $entity->credentials = $credentials;
        $entity->settings = $settings;

        return $entity;
    }

    /**
     * @param array<string, mixed>|null $params
     * @param array<string, mixed>|null $settings
     */
    private function channelConfig(
        bool $enabled = true,
        ?array $params = null,
        ?array $settings = null
    ): PaymentChannelConfigEntity {
        $entity = new PaymentChannelConfigEntity();
        $entity->setUniqueIdentifier('channel-config-1');
        $entity->providerTechnicalName = self::PROVIDER;
        $entity->channelTechnicalName = self::CHANNEL;
        $entity->enabled = $enabled;
        $entity->params = $params;
        $entity->settings = $settings;

        return $entity;
    }

    /**
     * @return EntitySearchResult<PaymentAppProviderCollection>
     */
    private function appProviders(PaymentAppProviderEntity ...$entities): EntitySearchResult
    {
        return $this->searchResult(
            new PaymentAppProviderCollection($entities),
            $entities === [] ? 'payment_app_provider' : $entities[0]->getApiAlias()
        );
    }

    /**
     * @return EntitySearchResult<PaymentAppChannelCollection>
     */
    private function appChannels(PaymentAppChannelEntity ...$entities): EntitySearchResult
    {
        return $this->searchResult(
            new PaymentAppChannelCollection($entities),
            $entities === [] ? 'payment_app_channel' : $entities[0]->getApiAlias()
        );
    }

    /**
     * @return EntitySearchResult<PaymentProviderConfigCollection>
     */
    private function providerConfigs(PaymentProviderConfigEntity ...$entities): EntitySearchResult
    {
        return $this->searchResult(
            new PaymentProviderConfigCollection($entities),
            $entities === [] ? 'payment_provider_config' : $entities[0]->getApiAlias()
        );
    }

    /**
     * @return EntitySearchResult<PaymentChannelConfigCollection>
     */
    private function channelConfigs(PaymentChannelConfigEntity ...$entities): EntitySearchResult
    {
        return $this->searchResult(
            new PaymentChannelConfigCollection($entities),
            $entities === [] ? 'payment_channel_config' : $entities[0]->getApiAlias()
        );
    }

    /**
     * @template TCollection of EntityCollection
     *
     * @param TCollection $entities
     *
     * @return EntitySearchResult<TCollection>
     */
    private function searchResult(EntityCollection $entities, string $entity): EntitySearchResult
    {
        return new EntitySearchResult(
            $entity,
            $entities->count(),
            $entities,
            null,
            new Criteria(),
            Context::createDefaultContext()
        );
    }

    /**
     * @template TCollection of EntityCollection
     *
     * @param EntitySearchResult<TCollection> $result
     *
     * @return EntityRepository<TCollection>
     */
    private function repository(EntitySearchResult $result): EntityRepository
    {
        $repository = static::createStub(EntityRepository::class);
        $repository->method('search')->willReturn($result);

        return $repository;
    }

    /**
     * @param EntitySearchResult<PaymentAppProviderCollection>|null $appProviders
     * @param EntitySearchResult<PaymentProviderConfigCollection>|null $providerConfigs
     * @param EntitySearchResult<PaymentAppChannelCollection>|null $appChannels
     * @param EntitySearchResult<PaymentChannelConfigCollection>|null $channelConfigs
     */
    private function resolver(
        ?PaymentChannel $channel = null,
        ?EntitySearchResult $appProviders = null,
        ?EntitySearchResult $providerConfigs = null,
        ?EntitySearchResult $appChannels = null,
        ?EntitySearchResult $channelConfigs = null
    ): PaymentConfigResolver {
        $channel ??= $this->channel();

        $providers = new PaymentProviderRegistry([
            new PaymentProvider(
                self::PROVIDER,
                'Stripe',
                [],
                [self::CHANNEL => $channel],
                new PaymentGatewayFactory('stripe', StripeApi::class, []),
                new StripeNotificationExtractor()
            ),
        ]);

        return new PaymentConfigResolver(
            $providers,
            new PaymentChannelRegistry($providers),
            $this->repository($appProviders ?? $this->appProviders()),
            $this->repository($providerConfigs ?? $this->providerConfigs()),
            $this->repository($appChannels ?? $this->appChannels()),
            $this->repository($channelConfigs ?? $this->channelConfigs()),
            $this->cipher
        );
    }
}
