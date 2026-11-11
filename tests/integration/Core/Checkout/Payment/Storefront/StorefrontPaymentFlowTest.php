<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Checkout\Payment\Storefront;

use Payum\Core\GatewayInterface;
use Payum\Core\Request\Capture;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Payment\Storefront\StorefrontPaymentContextResolver;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\App\PaymentCredentialCipher;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayFactory;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\Gateway\Struct\PaymentHandover;
use Shopwell\Core\System\Payment\Gateway\Struct\PaymentStartResult;
use Shopwell\Core\System\Payment\Order\PaymentOrderService;
use Shopwell\Core\System\Payment\Order\PaymentOrderStarter;
use Shopwell\Core\System\Payment\Order\Struct\PaymentOrderRequest;
use Shopwell\Core\System\Payment\Product\PaymentInteraction;
use Shopwell\Core\System\Payment\Product\PaymentScene;
use Shopwell\Core\System\Payment\Provider\PaymentProvider;
use Shopwell\Core\System\Payment\Provider\PaymentProviderRegistry;
use Shopwell\Core\System\Payment\Provider\ResolvedPaymentConfig;
use Shopwell\Core\System\Payment\Routing\PaymentContext;
use Shopwell\Core\System\Payment\Routing\PaymentRoute;
use Shopwell\Core\System\Payment\Routing\PaymentRouteResolver;
use Symfony\Component\HttpFoundation\Request;

/**
 * Real provider declarations, merchant grants, routing and durable attempts with provider I/O replaced.
 *
 * @internal
 */
#[Package('checkout')]
class StorefrontPaymentFlowTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private PaymentAppEntity $app;

    /**
     * @var array<string, string>
     */
    private array $accounts = [];

    protected function setUp(): void
    {
        $context = Context::createDefaultContext();
        $container = static::getContainer();
        $cipher = $container->get(PaymentCredentialCipher::class);
        $id = Uuid::randomHex();
        $container->get('payment_app.repository')->create([[
            'id' => $id,
            'technicalName' => 'storefront_flow_' . $id,
            'name' => 'Storefront payment fixture',
            'type' => PaymentAppEntity::TYPE_EXTERNAL,
            'active' => true,
            'appSecret' => $cipher->encryptValue('test-only-secret'),
        ]], $context);
        foreach ($container->get(PaymentProviderRegistry::class)->all() as $provider) {
            $accountId = Uuid::randomHex();
            $this->accounts[$provider->technicalName] = $accountId;
            $container->get('payment_account.repository')->create([[
                'id' => $accountId,
                'providerTechnicalName' => $provider->technicalName,
                'name' => $provider->technicalName . ' test merchant',
                'active' => true,
                'ownerAppId' => $id,
                'enabledProducts' => array_keys($provider->products),
                'credentials' => $cipher->encrypt(['fixture' => 'no-external-network']),
            ]], $context);
            $container->get('payment_app_account.repository')->create([[
                'paymentAppId' => $id, 'accountId' => $accountId, 'enabled' => true, 'position' => 0,
            ]], $context);
            foreach ($provider->products as $product) {
                $container->get('payment_app_channel.repository')->create([[
                    'paymentAppId' => $id, 'channelTechnicalName' => $product->technicalName, 'enabled' => true,
                ]], $context);
            }
        }
        $app = $container->get('payment_app.repository')->search(new Criteria([$id]), $context)->getEntities()->first();
        static::assertInstanceOf(PaymentAppEntity::class, $app);
        $this->app = $app;
    }

    #[DataProvider('browserPayments')]
    public function testBrowserSelectsAnExecutableProductAndReplaysItsHandover(string $agent, string $method, string $product, string $interaction): void
    {
        $request = new Request(server: ['HTTP_USER_AGENT' => $agent]);
        $context = static::getContainer()->get(StorefrontPaymentContextResolver::class)->resolve($request, 'CNY');
        $route = static::getContainer()->get(PaymentRouteResolver::class)->resolveMethod($this->app, $method, $context);
        static::assertSame($product, $route->config->product->technicalName);
        static::assertSame($interaction, $route->interaction);

        $this->payAndReplay($route);
    }

    /**
     * @return iterable<string, array{string, string, string, string}>
     */
    public static function browserPayments(): iterable
    {
        yield 'desktop Alipay uses website payment' => ['Mozilla/5.0 Windows', 'alipay', 'alipay_web', 'redirect'];
        yield 'mobile Alipay uses H5' => ['Mozilla/5.0 iPhone Mobile', 'alipay', 'alipay_h5', 'redirect'];
        yield 'Alipay browser uses H5' => ['Mozilla/5.0 AlipayClient', 'alipay', 'alipay_h5', 'redirect'];
        yield 'desktop WeChat uses a QR code' => ['Mozilla/5.0 Windows', 'wechat', 'wechat_native', 'qrcode'];
        yield 'mobile WeChat uses H5' => ['Mozilla/5.0 Android Mobile', 'wechat', 'wechat_h5', 'redirect'];
    }

    public function testDesktopCanUseAlipayQrWhenTheMerchantDisablesWebsitePayment(): void
    {
        static::getContainer()->get('payment_account.repository')->update([[
            'id' => $this->accounts['alipay'], 'enabledProducts' => ['alipay_qr'],
        ]], Context::createDefaultContext());
        $context = new PaymentContext('CNY', new PaymentScene('browser', 'web', 'browser'));
        $route = static::getContainer()->get(PaymentRouteResolver::class)->resolveMethod($this->app, 'alipay', $context);
        static::assertSame('alipay_qr', $route->config->product->technicalName);
        $this->payAndReplay($route);
    }

    public function testWeChatUserAgentAloneDoesNotAdvertiseAnUnusableJsapiPayment(): void
    {
        $context = static::getContainer()->get(StorefrontPaymentContextResolver::class)->resolve(new Request(server: ['HTTP_USER_AGENT' => 'MicroMessenger']), 'CNY');
        $options = static::getContainer()->get(PaymentRouteResolver::class)->options($this->app, $context);
        static::assertNotContains('wechat', array_map(static fn (PaymentRoute $route): string => $route->config->product->method, $options));
    }

    public function testForeignCurrencyOffersOnlyProductsSupportingThatCurrency(): void
    {
        $context = new PaymentContext('USD', new PaymentScene('browser', 'web', 'browser'));
        $options = static::getContainer()->get(PaymentRouteResolver::class)->options($this->app, $context);
        $methods = array_map(static fn (PaymentRoute $route): string => $route->config->product->method, $options);
        sort($methods);
        static::assertSame([], $methods);
    }

    /**
     * @param array<string, string> $identity
     */
    #[DataProvider('sdkPayments')]
    public function testTrustedSdkClientAndAccountScopedIdentityCanUseTheirOwnProducts(string $provider, string $product, PaymentScene $scene, array $identity): void
    {
        $context = new PaymentContext(
            'CNY',
            $scene,
            supportedInteractions: [PaymentInteraction::SDK],
            supportedSdkProducts: [$product],
            accountRuntimeParams: [$this->accounts[$provider] => [$product => $identity]],
        );
        $route = static::getContainer()->get(PaymentRouteResolver::class)->resolveProduct($this->app, $product, $context);
        static::assertSame(PaymentInteraction::SDK, $route->interaction);
        $this->payAndReplay($route);
    }

    /**
     * @return iterable<string, array{string, string, PaymentScene, array<string, string>}>
     */
    public static function sdkPayments(): iterable
    {
        yield 'WeChat public account bridge' => ['wechat', 'wechat_jsapi', new PaymentScene('browser', 'wap', 'wechat'), ['openid' => 'test-merchant-scoped-openid']];
        yield 'WeChat mini program' => ['wechat', 'wechat_mini', new PaymentScene('mini_program', 'mini', 'wechat'), ['openid' => 'test-mini-program-openid']];
        yield 'WeChat native app' => ['wechat', 'wechat_app', new PaymentScene('native_app', 'app'), []];
        yield 'Alipay mini program' => ['alipay', 'alipay_mini', new PaymentScene('mini_program', 'mini', 'alipay'), ['buyer_id' => 'test-alipay-buyer']];
        yield 'Alipay native app' => ['alipay', 'alipay_app', new PaymentScene('native_app', 'app'), []];
    }

    private function payAndReplay(PaymentRoute $route): void
    {
        $handover = match ($route->interaction) {
            PaymentInteraction::QRCODE => new PaymentHandover('qrcode', extra: ['qr_code' => 'https://provider.example.test/fixture']),
            PaymentInteraction::SDK => new PaymentHandover('sdk', extra: ['fixture' => 'client-sdk-parameters']),
            default => new PaymentHandover('redirect', 'https://provider.example.test/fixture'),
        };
        $gateway = $this->createMock(GatewayInterface::class);
        $gateway->expects($this->once())->method('execute')->willReturnCallback(static function (Capture $capture) use ($handover): void {
            $capture->getModel()[PaymentGatewayModel::RESULT] = new PaymentStartResult($handover, 'fixture-trade');
        });
        $factory = static::createStub(PaymentGatewayFactory::class);
        $factory->method('createForPayment')->willReturn($gateway);
        $original = $route->config;
        $provider = $original->provider;
        $configuration = new ResolvedPaymentConfig(
            new PaymentProvider($provider->technicalName, $provider->label, $provider->credentials, $provider->products, $factory, $provider->notificationExtractor),
            $original->product,
            $original->credentials,
            $original->params,
            $original->runtimeParams,
            $original->settings,
            $original->accountId,
            $original->priority,
            $original->accountFingerprint,
        );
        $orders = static::getContainer()->get(PaymentOrderService::class);
        $starter = static::getContainer()->get(PaymentOrderStarter::class);
        $order = $orders->create($this->app, new PaymentOrderRequest('storefront-' . Uuid::randomHex(), 1299, 'CNY', 'Fixture purchase'));
        $started = $starter->start($this->app, $order, new PaymentRoute($configuration, $route->context, $route->interaction));
        static::assertSame($handover->jsonSerialize(), $started->params->jsonSerialize());
        $persisted = $orders->getById($order->id);
        static::assertNotNull($persisted->activeAttempt);
        static::assertSame($original->accountId, $persisted->activeAttempt->accountId);
        static::assertSame($original->product->technicalName, $persisted->activeAttempt->channelTechnicalName);
        $replayed = $starter->replay($persisted);
        static::assertNotNull($replayed);
        static::assertSame($handover->jsonSerialize(), $replayed->params->jsonSerialize());
        $starter->start($this->app, $persisted, new PaymentRoute($configuration, $route->context, $route->interaction));
        static::assertCount(1, $orders->getById($order->id)->attempts ?? []);
    }
}
