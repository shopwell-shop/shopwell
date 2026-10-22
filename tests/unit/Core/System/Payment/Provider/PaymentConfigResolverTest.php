<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Provider;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
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
        $app->type = PaymentAppEntity::TYPE_EXTERNAL;
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
            appProviders: $this->appProviders($this->appProvider(credentials: null)),
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
            appProviders: $this->appProviders($this->appProvider(paymentAppId: 'platform-app-1', settings: ['from_app_provider' => 'app'])),
            appChannels: $this->appChannels($this->appChannel(settings: ['from_app_channel' => 'app'])),
            channelConfigs: $this->channelConfigs($this->channelConfig(
                params: $this->cipher->encrypt(['appid' => 'wx-platform']),
                settings: ['from_channel_config' => 'platform']
            )),
            providerConfigs: $this->providerConfigs($this->providerConfig(
                credentials: $this->cipher->encrypt(['secret_key' => 'sk_platform'])
            ))
        );

        $resolved = $resolver->resolve($this->platformApp(), self::CHANNEL, 'CNY', 'web', ['openid' => 'o-1']);

        static::assertSame(self::PROVIDER, $resolved->provider->technicalName);
        static::assertSame(self::CHANNEL, $resolved->channel->technicalName);
        static::assertSame(['secret_key' => 'sk_platform'], $resolved->credentials);
        static::assertSame(['appid' => 'wx-platform'], $resolved->params);
        static::assertSame(['openid' => 'o-1'], $resolved->runtimeParams);
        // the tenant of the platform collects on the platform account, which is its own one
        static::assertNull($resolved->sourceAppId);
        static::assertFalse($resolved->isInherited());
        static::assertSame(
            ['from_channel_config' => 'platform', 'from_app_provider' => 'app', 'from_app_channel' => 'app'],
            $resolved->settings
        );
        static::assertSame('sk_platform', $resolved->require('secret_key'));
        static::assertSame('wx-platform', $resolved->requireParam('appid'));
        static::assertSame('app', $resolved->setting('from_app_provider'));
        static::assertSame('o-1', $resolved->runtimeParam('openid'));
    }

    public function testResolveUsesTheOwnCredentialsOfTheApp(): void
    {
        $resolver = $this->resolver(
            appProviders: $this->appProviders($this->appProvider(
                credentials: $this->cipher->encrypt(['secret_key' => 'sk_own'])
            )),
            appChannels: $this->appChannels($this->appChannel(
                params: $this->cipher->encrypt(['appid' => 'wx-own'])
            )),
            channelConfigs: $this->channelConfigs($this->channelConfig())
        );

        $resolved = $resolver->resolve($this->app, self::CHANNEL, 'CNY');

        static::assertNull($resolved->sourceAppId);
        static::assertFalse($resolved->isInherited());
        static::assertSame(['secret_key' => 'sk_own'], $resolved->credentials);
        static::assertSame(['appid' => 'wx-own'], $resolved->params);
    }

    /**
     * The account of another app is inherited along `sourceAppId`, and the chain ends at the tenant of
     * the platform - the app collects on the platform account without holding any credentials itself.
     */
    public function testResolveInheritsTheAccountOfThePlatformTenant(): void
    {
        $platform = $this->platformApp();

        $resolver = $this->resolver(
            appProviders: $this->appProviders(
                $this->appProvider(sourceAppId: $platform->id, sourceApp: $platform),
                $this->appProvider(enabled: true, paymentAppId: $platform->id, providerTechnicalName: self::PROVIDER)
            ),
            appChannels: $this->appChannels($this->appChannel()),
            channelConfigs: $this->channelConfigs($this->channelConfig(
                params: $this->cipher->encrypt(['appid' => 'wx-platform'])
            )),
            providerConfigs: $this->providerConfigs($this->providerConfig(
                credentials: $this->cipher->encrypt(['secret_key' => 'sk_platform'])
            ))
        );

        $resolved = $resolver->resolve($this->app, self::CHANNEL, 'CNY');

        static::assertSame(['secret_key' => 'sk_platform'], $resolved->credentials);
        static::assertSame(['appid' => 'wx-platform'], $resolved->params);
        static::assertSame($platform->id, $resolved->sourceAppId);
        static::assertTrue($resolved->isInherited());
    }

    /**
     * Two hops: the app inherits from an app that inherits from the platform tenant again.
     */
    public function testResolveWalksTheWholeAccountSourceChain(): void
    {
        $platform = $this->platformApp();

        $middle = new PaymentAppEntity();
        $middle->id = 'app-2';
        $middle->technicalName = 'middle';
        $middle->type = PaymentAppEntity::TYPE_EXTERNAL;

        $resolver = $this->resolver(
            appProviders: $this->appProviders(
                $this->appProvider(sourceAppId: $middle->id, sourceApp: $middle),
                $this->appProvider(paymentAppId: $middle->id, sourceAppId: $platform->id, sourceApp: $platform),
                $this->appProvider(paymentAppId: $platform->id)
            ),
            appChannels: $this->appChannels($this->appChannel()),
            channelConfigs: $this->channelConfigs($this->channelConfig()),
            providerConfigs: $this->providerConfigs($this->providerConfig(
                credentials: $this->cipher->encrypt(['secret_key' => 'sk_platform'])
            ))
        );

        $resolved = $resolver->resolve($this->app, self::CHANNEL, 'CNY');

        static::assertSame(['secret_key' => 'sk_platform'], $resolved->credentials);
        static::assertSame($platform->id, $resolved->sourceAppId);
    }

    /**
     * A chain that names an app twice has no account at all, so it is refused instead of being walked
     * to its hop limit.
     */
    public function testResolveRejectsAnAccountSourceCircle(): void
    {
        $other = new PaymentAppEntity();
        $other->id = 'app-2';
        $other->technicalName = 'other';
        $other->type = PaymentAppEntity::TYPE_EXTERNAL;

        $resolver = $this->resolver(
            appProviders: $this->appProviders(
                $this->appProvider(sourceAppId: $other->id, sourceApp: $other),
                $this->appProvider(paymentAppId: $other->id, sourceAppId: 'app-1')
            ),
            appChannels: $this->appChannels($this->appChannel()),
            channelConfigs: $this->channelConfigs($this->channelConfig())
        );

        $this->expectExceptionObject(PaymentException::providerInheritanceCycle(self::PROVIDER, 'app'));

        $resolver->resolve($this->app, self::CHANNEL, 'CNY');
    }

    public function testResolveRejectsAnAppThatInheritsFromItself(): void
    {
        $resolver = $this->resolver(
            appProviders: $this->appProviders($this->appProvider(sourceAppId: 'app-1')),
            appChannels: $this->appChannels($this->appChannel()),
            channelConfigs: $this->channelConfigs($this->channelConfig())
        );

        $this->expectExceptionObject(PaymentException::providerInheritanceCycle(self::PROVIDER, 'app'));

        $resolver->resolve($this->app, self::CHANNEL, 'CNY');
    }

    public function testResolveRejectsAnAccountSourceThatDoesNotExistAnymore(): void
    {
        $resolver = $this->resolver(
            appProviders: $this->appProviders($this->appProvider(sourceAppId: 'deleted-app')),
            appChannels: $this->appChannels($this->appChannel()),
            channelConfigs: $this->channelConfigs($this->channelConfig())
        );

        $this->expectExceptionObject(PaymentException::providerSourceAppMissing(self::PROVIDER, 'app', 'deleted-app'));

        $resolver->resolve($this->app, self::CHANNEL, 'CNY');
    }

    public function testResolveRejectsADisabledAuthorizationOnTheWayToThePlatform(): void
    {
        $platform = $this->platformApp();

        $resolver = $this->resolver(
            appProviders: $this->appProviders(
                $this->appProvider(sourceAppId: $platform->id, sourceApp: $platform),
                $this->appProvider(paymentAppId: $platform->id, enabled: false)
            ),
            appChannels: $this->appChannels($this->appChannel()),
            channelConfigs: $this->channelConfigs($this->channelConfig())
        );

        $this->expectExceptionObject(
            PaymentException::providerNotEnabled(self::PROVIDER, PaymentAppEntity::PLATFORM_TECHNICAL_NAME)
        );

        $resolver->resolve($this->app, self::CHANNEL, 'CNY');
    }

    /**
     * A payout notification has no channel in its url, so the credentials are resolved per interface.
     * They are handed to the verifier in clear - the stored value is ciphertext, and a verifier that
     * was given the ciphertext could never validate anything.
     */
    public function testResolveProviderCredentialsReturnsTheDecryptedPlatformCredentials(): void
    {
        $resolver = $this->resolver(
            appProviders: $this->appProviders($this->appProvider(paymentAppId: 'platform-app-1')),
            providerConfigs: $this->providerConfigs($this->providerConfig(
                credentials: $this->cipher->encrypt(['webhook_secret' => 'whsec_platform'])
            ))
        );

        static::assertSame(
            ['webhook_secret' => 'whsec_platform'],
            $resolver->resolveProviderCredentials($this->platformApp(), self::PROVIDER)
        );
    }

    /**
     * An app that collects on an account of its own signs its notifications with its own keys - the
     * platform credentials would not verify them.
     */
    public function testResolveProviderCredentialsUsesTheOwnCredentials(): void
    {
        $resolver = $this->resolver(
            appProviders: $this->appProviders($this->appProvider(
                credentials: $this->cipher->encrypt(['webhook_secret' => 'whsec_own'])
            ))
        );

        static::assertSame(
            ['webhook_secret' => 'whsec_own'],
            $resolver->resolveProviderCredentials($this->app, self::PROVIDER)
        );
    }

    /**
     * The verification follows the account source like the payment does: an app that inherits the
     * account of another app is verified with the keys of that app.
     */
    public function testResolveProviderCredentialsFollowsTheAccountSourceChain(): void
    {
        $platform = $this->platformApp();

        $resolver = $this->resolver(
            appProviders: $this->appProviders(
                $this->appProvider(sourceAppId: $platform->id, sourceApp: $platform),
                $this->appProvider(paymentAppId: $platform->id)
            ),
            providerConfigs: $this->providerConfigs($this->providerConfig(
                credentials: $this->cipher->encrypt(['webhook_secret' => 'whsec_platform'])
            ))
        );

        static::assertSame(
            ['webhook_secret' => 'whsec_platform'],
            $resolver->resolveProviderCredentials($this->app, self::PROVIDER)
        );
    }

    public function testResolveProviderCredentialsRejectsAProviderTheAppIsNotAuthorizedFor(): void
    {
        $this->expectExceptionObject(PaymentException::providerNotEnabled(self::PROVIDER, 'app'));

        $this->resolver()->resolveProviderCredentials($this->app, self::PROVIDER);
    }

    public function testResolveProviderCredentialsRejectsADisabledAuthorization(): void
    {
        $resolver = $this->resolver(
            appProviders: $this->appProviders($this->appProvider(enabled: false))
        );

        $this->expectExceptionObject(PaymentException::providerNotEnabled(self::PROVIDER, 'app'));

        $resolver->resolveProviderCredentials($this->app, self::PROVIDER);
    }

    public function testResolveProviderCredentialsRejectsAnEmptyConfiguration(): void
    {
        $resolver = $this->resolver(
            appProviders: $this->appProviders($this->appProvider()),
            providerConfigs: $this->providerConfigs($this->providerConfig(credentials: $this->cipher->encrypt([])))
        );

        $this->expectExceptionObject(PaymentException::providerNotConfigured(self::PROVIDER));

        $resolver->resolveProviderCredentials($this->app, self::PROVIDER);
    }

    public function testEnabledForReturnsNothingWithoutAnyAuthorization(): void
    {
        static::assertSame([], $this->resolver()->enabledFor($this->app));
    }

    public function testEnabledForListsTheChannelsOfTheAuthorizedProviders(): void
    {
        $resolver = $this->resolver(
            channel: $this->channel(runtimeParams: ['openid' => true]),
            appProviders: $this->appProviders($this->appProvider(sourceAppId: 'platform-app-1')),
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
                // the app inherits the account of the tenant of the platform for this interface
                'source_app_id' => 'platform-app-1',
                'inherited' => true,
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
        ?string $sourceAppId = null,
        ?array $credentials = null,
        ?array $settings = null,
        string $providerTechnicalName = self::PROVIDER,
        string $paymentAppId = 'app-1',
        ?PaymentAppEntity $sourceApp = null
    ): PaymentAppProviderEntity {
        $entity = new PaymentAppProviderEntity();
        $entity->setUniqueIdentifier('app-provider-' . $paymentAppId);
        $entity->paymentAppId = $paymentAppId;
        $entity->providerTechnicalName = $providerTechnicalName;
        $entity->enabled = $enabled;
        $entity->sourceAppId = $sourceAppId;
        $entity->sourceApp = $sourceApp;
        $entity->credentials = $credentials;
        $entity->settings = $settings;

        return $entity;
    }

    /**
     * The tenant of the platform, the root of every account source chain.
     */
    private function platformApp(): PaymentAppEntity
    {
        $app = new PaymentAppEntity();
        $app->id = 'platform-app-1';
        $app->setUniqueIdentifier($app->id);
        $app->technicalName = PaymentAppEntity::PLATFORM_TECHNICAL_NAME;
        $app->type = PaymentAppEntity::TYPE_PLATFORM;

        return $app;
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
     * The authorizations are read once per app of an account source chain, so this repository answers
     * the row of the app the query filtered for instead of always the first one.
     *
     * @param EntitySearchResult<PaymentAppProviderCollection> $result
     *
     * @return EntityRepository<PaymentAppProviderCollection>
     */
    private function appProviderRepository(EntitySearchResult $result): EntityRepository
    {
        $repository = static::createStub(EntityRepository::class);
        $repository->method('search')->willReturnCallback(
            function (Criteria $criteria) use ($result): EntitySearchResult {
                $appId = null;

                foreach ($criteria->getFilters() as $filter) {
                    if ($filter instanceof EqualsFilter && $filter->getField() === 'paymentAppId') {
                        $appId = $filter->getValue();
                    }
                }

                $entities = $result->getEntities()->filter(
                    static fn (PaymentAppProviderEntity $row): bool => $appId === null || $row->paymentAppId === $appId
                );

                return $this->searchResult($entities, 'payment_app_provider');
            }
        );

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
            $this->appProviderRepository($appProviders ?? $this->appProviders()),
            $this->repository($providerConfigs ?? $this->providerConfigs()),
            $this->repository($appChannels ?? $this->appChannels()),
            $this->repository($channelConfigs ?? $this->channelConfigs()),
            $this->cipher
        );
    }
}
