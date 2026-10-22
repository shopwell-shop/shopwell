<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Payment\Gateway;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Payment\Aggregate\PaymentMethodGatewayChannel\PaymentMethodGatewayChannelEntity;
use Shopwell\Core\Checkout\Payment\Gateway\PaymentGatewayHandler;
use Shopwell\Core\Checkout\Payment\Gateway\PaymentMethodCollectability;
use Shopwell\Core\Checkout\Payment\PaymentMethodCollection;
use Shopwell\Core\Checkout\Payment\PaymentMethodEntity;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppChannel\PaymentAppChannelEntity;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayFactory;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationExtractorInterface;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentChannel;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentChannelRegistry;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentFlow;
use Shopwell\Core\System\Payment\Provider\PaymentChannelConfigEntity;
use Shopwell\Core\System\Payment\Provider\PaymentProvider;
use Shopwell\Core\System\Payment\Provider\PaymentProviderConfigEntity;
use Shopwell\Core\System\Payment\Provider\PaymentProviderRegistry;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(PaymentMethodCollectability::class)]
class PaymentMethodCollectabilityTest extends TestCase
{
    private const INVOICE_HANDLER = 'Shopwell\Core\Checkout\Payment\Cart\PaymentHandler\InvoicePayment';

    public function testAMethodThatDoesNotCollectThroughTheGatewayIsNeverTouched(): void
    {
        $collectability = $this->collectability();

        $methods = $this->methods([
            $this->paymentMethod('invoice-id', self::INVOICE_HANDLER),
        ]);

        static::assertSame(['invoice-id'], $this->ids($collectability->filter($methods, Context::createDefaultContext())));
    }

    /**
     * The interesting case: a method that dispatches on the gateway handler but was never bound has
     * nothing to collect through, and the checkout would throw instead of paying.
     */
    public function testAMethodWithoutABindingIsRemoved(): void
    {
        $collectability = $this->collectability();

        $methods = $this->methods([
            $this->paymentMethod('gateway-id', PaymentGatewayHandler::class),
        ]);

        static::assertSame([], $this->ids($collectability->filter($methods, Context::createDefaultContext())));
    }

    public function testABoundMethodOfAConfiguredInterfaceIsKept(): void
    {
        $collectability = $this->collectability(
            bindings: [$this->binding('gateway-id')],
            channelConfigs: [$this->channelConfig()],
            providerConfigs: [$this->providerConfig()],
        );

        $methods = $this->methods([$this->paymentMethod('gateway-id', PaymentGatewayHandler::class)]);

        static::assertSame(['gateway-id'], $this->ids($collectability->filter($methods, Context::createDefaultContext())));
    }

    /**
     * A channel of an extension that is not installed anymore is still stored in the binding table, but
     * no code can open a payment through it.
     */
    public function testABoundMethodOfAnUnknownChannelIsRemoved(): void
    {
        $collectability = $this->collectability(
            bindings: [$this->binding('gateway-id', channel: 'stripe_card')],
            channelConfigs: [$this->channelConfig('stripe_card')],
            providerConfigs: [$this->providerConfig()],
        );

        $methods = $this->methods([$this->paymentMethod('gateway-id', PaymentGatewayHandler::class)]);

        static::assertSame([], $this->ids($collectability->filter($methods, Context::createDefaultContext())));
    }

    public function testABoundMethodOfADisabledChannelIsRemoved(): void
    {
        $collectability = $this->collectability(
            bindings: [$this->binding('gateway-id')],
            channelConfigs: [$this->channelConfig(enabled: false)],
            providerConfigs: [$this->providerConfig()],
        );

        $methods = $this->methods([$this->paymentMethod('gateway-id', PaymentGatewayHandler::class)]);

        static::assertSame([], $this->ids($collectability->filter($methods, Context::createDefaultContext())));
    }

    public function testABoundMethodOfAChannelWithoutConfigurationIsRemoved(): void
    {
        $collectability = $this->collectability(
            bindings: [$this->binding('gateway-id')],
            channelConfigs: [],
            providerConfigs: [$this->providerConfig()],
        );

        $methods = $this->methods([$this->paymentMethod('gateway-id', PaymentGatewayHandler::class)]);

        static::assertSame([], $this->ids($collectability->filter($methods, Context::createDefaultContext())));
    }

    public function testABoundMethodOfAnInterfaceWithoutMerchantAccountIsRemoved(): void
    {
        foreach ([
            $this->providerConfig(credentials: []),
            $this->providerConfig(enabled: false),
        ] as $providerConfig) {
            $collectability = $this->collectability(
                bindings: [$this->binding('gateway-id')],
                channelConfigs: [$this->channelConfig()],
                providerConfigs: [$providerConfig],
            );

            $methods = $this->methods([$this->paymentMethod('gateway-id', PaymentGatewayHandler::class)]);

            static::assertSame([], $this->ids($collectability->filter($methods, Context::createDefaultContext())));
        }
    }

    public function testOnlyTheMethodsThatCannotCollectAreRemoved(): void
    {
        $collectability = $this->collectability(
            bindings: [$this->binding('gateway-id')],
            channelConfigs: [$this->channelConfig()],
            providerConfigs: [$this->providerConfig()],
        );

        $methods = $this->methods([
            $this->paymentMethod('invoice-id', self::INVOICE_HANDLER),
            $this->paymentMethod('gateway-id', PaymentGatewayHandler::class),
            $this->paymentMethod('unbound-gateway-id', PaymentGatewayHandler::class),
        ]);

        static::assertSame(
            ['invoice-id', 'gateway-id'],
            $this->ids($collectability->filter($methods, Context::createDefaultContext()))
        );
    }

    /**
     * The channel declares the currencies it can process. alipay_h5 and wechat_h5 for instance only
     * collect in CNY, so a shop paying in EUR cannot be served by them.
     */
    public function testABoundMethodOfAChannelWithoutTheShopCurrencyIsRemoved(): void
    {
        $collectability = $this->collectability(
            bindings: [$this->binding('gateway-id')],
            channelConfigs: [$this->channelConfig()],
            providerConfigs: [$this->providerConfig()],
            currencies: ['CNY'],
        );

        $methods = $this->methods([$this->paymentMethod('gateway-id', PaymentGatewayHandler::class)]);

        static::assertSame(
            [],
            $this->ids($collectability->filter($methods, Context::createDefaultContext(), 'EUR'))
        );
        static::assertSame(
            ['gateway-id'],
            $this->ids($collectability->filter($methods, Context::createDefaultContext(), 'CNY'))
        );
    }

    /**
     * A channel that is opened by an sdk cannot be handed a storefront payer at all. A binding that was
     * written before the binder refused those channels is just as unusable.
     */
    public function testABoundMethodOfAnSdkChannelIsRemoved(): void
    {
        $collectability = $this->collectability(
            bindings: [$this->binding('gateway-id')],
            channelConfigs: [$this->channelConfig()],
            providerConfigs: [$this->providerConfig()],
            flow: PaymentFlow::SDK,
        );

        $methods = $this->methods([$this->paymentMethod('gateway-id', PaymentGatewayHandler::class)]);

        static::assertSame([], $this->ids($collectability->filter($methods, Context::createDefaultContext())));
    }

    /**
     * The channel declares the devices it can be opened on. wechat_h5 is declared for `wap` only, so a
     * desktop request must not be offered it: the payer would be handed to a page their browser cannot
     * open, and only learn that after pressing "buy".
     */
    public function testABoundMethodOfAChannelForAnotherDeviceIsRemoved(): void
    {
        $collectability = $this->collectability(
            bindings: [$this->binding('gateway-id')],
            channelConfigs: [$this->channelConfig()],
            providerConfigs: [$this->providerConfig()],
            devices: [PaymentChannel::DEVICE_WAP],
        );

        $methods = $this->methods([$this->paymentMethod('gateway-id', PaymentGatewayHandler::class)]);

        static::assertSame(
            [],
            $this->ids($collectability->filter($methods, Context::createDefaultContext(), null, PaymentChannel::DEVICE_WEB))
        );
        static::assertSame(
            ['gateway-id'],
            $this->ids($collectability->filter($methods, Context::createDefaultContext(), null, PaymentChannel::DEVICE_WAP))
        );
    }

    /**
     * A request that names no device keeps every channel: the store-api leaves the parameter to the
     * client, and a method hidden here would be hidden on the device the channel was declared for.
     */
    public function testABoundMethodOfAChannelIsKeptWhenTheRequestNamesNoDevice(): void
    {
        $collectability = $this->collectability(
            bindings: [$this->binding('gateway-id')],
            channelConfigs: [$this->channelConfig()],
            providerConfigs: [$this->providerConfig()],
            devices: [PaymentChannel::DEVICE_WAP],
        );

        $methods = $this->methods([$this->paymentMethod('gateway-id', PaymentGatewayHandler::class)]);

        static::assertSame(
            ['gateway-id'],
            $this->ids($collectability->filter($methods, Context::createDefaultContext()))
        );
    }

    /**
     * The money is collected on the merchant account of the app the binding names, so an app that is
     * switched off cannot collect any more - the checkout refuses the payment when it authorizes it.
     */
    public function testABoundMethodOfADisabledAppIsRemoved(): void
    {
        $collectability = $this->collectability(
            bindings: [$this->bindingOnApp('gateway-id', 'other-app-id')],
            channelConfigs: [$this->channelConfig()],
            providerConfigs: [$this->providerConfig()],
            apps: [$this->externalApp(active: false)],
            appChannels: [$this->appChannel('other-app-id')],
        );

        $methods = $this->methods([$this->paymentMethod('gateway-id', PaymentGatewayHandler::class)]);

        static::assertSame([], $this->ids($collectability->filter($methods, Context::createDefaultContext())));
    }

    /**
     * A binding to an app that is not there anymore cannot collect either. The cascade normally deletes
     * such a row together with its app, this is the answer for data that escaped it.
     */
    public function testABoundMethodOfAMissingAppIsRemoved(): void
    {
        $collectability = $this->collectability(
            bindings: [$this->bindingOnApp('gateway-id', 'other-app-id')],
            channelConfigs: [$this->channelConfig()],
            providerConfigs: [$this->providerConfig()],
            apps: [],
        );

        $methods = $this->methods([$this->paymentMethod('gateway-id', PaymentGatewayHandler::class)]);

        static::assertSame([], $this->ids($collectability->filter($methods, Context::createDefaultContext())));
    }

    /**
     * Every app but the tenant of the platform collects only what the merchant has switched on for it:
     * its authorizations are configured, not written by the checkout.
     */
    public function testABoundMethodOfAnotherAppIsKeptWhenTheAppOffersTheChannel(): void
    {
        $collectability = $this->collectability(
            bindings: [$this->bindingOnApp('gateway-id', 'other-app-id')],
            channelConfigs: [$this->channelConfig()],
            providerConfigs: [$this->providerConfig()],
            apps: [$this->externalApp()],
            appChannels: [$this->appChannel('other-app-id')],
        );

        $methods = $this->methods([$this->paymentMethod('gateway-id', PaymentGatewayHandler::class)]);

        static::assertSame(['gateway-id'], $this->ids($collectability->filter($methods, Context::createDefaultContext())));
    }

    public function testABoundMethodOfAnotherAppWithoutTheChannelIsRemoved(): void
    {
        $collectability = $this->collectability(
            bindings: [$this->bindingOnApp('gateway-id', 'other-app-id')],
            channelConfigs: [$this->channelConfig()],
            providerConfigs: [$this->providerConfig()],
            apps: [$this->externalApp()],
            appChannels: [$this->appChannel('other-app-id', enabled: false)],
        );

        $methods = $this->methods([$this->paymentMethod('gateway-id', PaymentGatewayHandler::class)]);

        static::assertSame([], $this->ids($collectability->filter($methods, Context::createDefaultContext())));
    }

    /**
     * @param list<PaymentMethodGatewayChannelEntity> $bindings
     * @param list<PaymentChannelConfigEntity> $channelConfigs
     * @param list<PaymentProviderConfigEntity> $providerConfigs
     * @param list<string> $currencies
     * @param list<string> $devices
     * @param list<PaymentAppEntity>|null $apps
     * @param list<PaymentAppChannelEntity>|null $appChannels
     */
    private function collectability(
        array $bindings = [],
        array $channelConfigs = [],
        array $providerConfigs = [],
        array $currencies = [],
        PaymentFlow $flow = PaymentFlow::REDIRECT,
        array $devices = [],
        ?array $apps = null,
        ?array $appChannels = null,
    ): PaymentMethodCollectability {
        return new PaymentMethodCollectability(
            $this->repository($bindings),
            $this->repository($channelConfigs),
            $this->repository($providerConfigs),
            $this->repository($apps ?? [PaymentGatewayFixture::platformAppEntity()]),
            $this->repository($appChannels ?? []),
            $this->channelRegistry($currencies, $flow, $devices),
        );
    }

    /**
     * An interface whose only channel declares the given currencies, flow and devices.
     *
     * @param list<string> $currencies
     * @param list<string> $devices
     */
    private function channelRegistry(
        array $currencies = [],
        PaymentFlow $flow = PaymentFlow::REDIRECT,
        array $devices = [],
    ): PaymentChannelRegistry {
        $provider = new PaymentProvider(
            technicalName: PaymentGatewayFixture::PROVIDER,
            label: 'Alipay',
            credentials: [],
            channels: [
                PaymentGatewayFixture::CHANNEL => new PaymentChannel(
                    technicalName: PaymentGatewayFixture::CHANNEL,
                    providerTechnicalName: PaymentGatewayFixture::PROVIDER,
                    label: 'Alipay H5',
                    flow: $flow,
                    currencies: $currencies,
                    devices: $devices,
                ),
            ],
            gatewayFactory: static::createStub(PaymentGatewayFactory::class),
            notificationExtractor: static::createStub(PaymentNotificationExtractorInterface::class),
        );

        return new PaymentChannelRegistry(new PaymentProviderRegistry([$provider]));
    }

    /**
     * @param list<Entity> $entities
     *
     * @phpstan-ignore missingType.generics (The stub serves every collection type the caller asserts.)
     */
    private function repository(array $entities): EntityRepository
    {
        $repository = static::createStub(EntityRepository::class);
        $repository->method('search')->willReturn(PaymentGatewayFixture::searchResult($entities));

        return $repository;
    }

    /**
     * @return list<string>
     */
    private function ids(PaymentMethodCollection $methods): array
    {
        return array_values($methods->getIds());
    }

    /**
     * @param list<PaymentMethodEntity> $methods
     */
    private function methods(array $methods): PaymentMethodCollection
    {
        return new PaymentMethodCollection($methods);
    }

    private function paymentMethod(string $id, string $handlerIdentifier): PaymentMethodEntity
    {
        $paymentMethod = new PaymentMethodEntity();
        $paymentMethod->setId($id);
        $paymentMethod->setUniqueIdentifier($id);
        $paymentMethod->setHandlerIdentifier($handlerIdentifier);
        $paymentMethod->setName($id);

        return $paymentMethod;
    }

    private function binding(string $paymentMethodId, string $channel = PaymentGatewayFixture::CHANNEL): PaymentMethodGatewayChannelEntity
    {
        $binding = new PaymentMethodGatewayChannelEntity();
        $binding->id = 'binding-of-' . $paymentMethodId;
        $binding->setUniqueIdentifier($binding->id);
        $binding->paymentMethodId = $paymentMethodId;
        $binding->paymentAppId = PaymentGatewayFixture::PLATFORM_APP_ID;
        $binding->channelTechnicalName = $channel;

        return $binding;
    }

    /**
     * A binding on an app that is not the tenant of the platform, i.e. one that collects on its own
     * merchant account and therefore has to offer the channel itself.
     */
    private function bindingOnApp(string $paymentMethodId, string $paymentAppId): PaymentMethodGatewayChannelEntity
    {
        $binding = $this->binding($paymentMethodId);
        $binding->paymentAppId = $paymentAppId;

        return $binding;
    }

    private function externalApp(string $id = 'other-app-id', bool $active = true): PaymentAppEntity
    {
        $app = new PaymentAppEntity();
        $app->id = $id;
        $app->setUniqueIdentifier($id);
        $app->technicalName = 'other-app';
        $app->name = 'Other app';
        $app->type = PaymentAppEntity::TYPE_EXTERNAL;
        $app->active = $active;
        $app->appSecret = 'secret';
        $app->settings = [];

        return $app;
    }

    private function appChannel(string $paymentAppId = PaymentGatewayFixture::PLATFORM_APP_ID, bool $enabled = true): PaymentAppChannelEntity
    {
        $appChannel = new PaymentAppChannelEntity();
        $appChannel->id = 'app-channel-of-' . $paymentAppId;
        $appChannel->setUniqueIdentifier($appChannel->id);
        $appChannel->paymentAppId = $paymentAppId;
        $appChannel->channelTechnicalName = PaymentGatewayFixture::CHANNEL;
        $appChannel->enabled = $enabled;

        return $appChannel;
    }

    /**
     * @param array<string, mixed>|null $credentials
     */
    private function providerConfig(
        bool $enabled = true,
        ?array $credentials = ['app_id' => 'platform-app-id'],
    ): PaymentProviderConfigEntity {
        $config = new PaymentProviderConfigEntity();
        $config->id = 'provider-config-id';
        $config->setUniqueIdentifier($config->id);
        $config->providerTechnicalName = PaymentGatewayFixture::PROVIDER;
        $config->enabled = $enabled;
        $config->credentials = $credentials;

        return $config;
    }

    private function channelConfig(
        string $channel = PaymentGatewayFixture::CHANNEL,
        bool $enabled = true,
    ): PaymentChannelConfigEntity {
        $config = new PaymentChannelConfigEntity();
        $config->id = 'channel-config-id';
        $config->setUniqueIdentifier($config->id);
        $config->providerTechnicalName = PaymentGatewayFixture::PROVIDER;
        $config->channelTechnicalName = $channel;
        $config->enabled = $enabled;

        return $config;
    }
}
