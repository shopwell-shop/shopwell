<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Routing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\Exception\PaymentProviderException;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayFactory;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationExtractorInterface;
use Shopwell\Core\System\Payment\Product\PaymentInteraction;
use Shopwell\Core\System\Payment\Product\PaymentProduct;
use Shopwell\Core\System\Payment\Product\PaymentProductRegistry;
use Shopwell\Core\System\Payment\Product\PaymentScene;
use Shopwell\Core\System\Payment\Provider\Capability\PaymentCapability;
use Shopwell\Core\System\Payment\Provider\Config\PaymentConfigResolver;
use Shopwell\Core\System\Payment\Provider\PaymentProvider;
use Shopwell\Core\System\Payment\Provider\PaymentProviderRegistry;
use Shopwell\Core\System\Payment\Provider\ResolvedPaymentConfig;
use Shopwell\Core\System\Payment\Routing\DefaultPaymentRoutingPolicy;
use Shopwell\Core\System\Payment\Routing\PaymentContext;
use Shopwell\Core\System\Payment\Routing\PaymentProductEligibility;
use Shopwell\Core\System\Payment\Routing\PaymentRoute;
use Shopwell\Core\System\Payment\Routing\PaymentRouteResolver;
use Shopwell\Core\System\Payment\Routing\PaymentRoutingPolicy;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentRouteResolver::class)]
class PaymentRouteResolverTest extends TestCase
{
    public function testSameMethodCanRouteAcrossProvidersAndExplicitMerchantAccounts(): void
    {
        $official = $this->product('official_h5', 'official');
        $aggregator = $this->product('aggregator_h5', 'aggregator');
        $policy = static::createMock(PaymentRoutingPolicy::class);
        $policy->method('getName')->willReturn('merchant');
        $policy->expects($this->once())->method('choose')->willReturnCallback(static function (array $routes): PaymentRoute {
            static::assertSame(['official-1', 'official-2', 'aggregator-1'], array_map(static fn (PaymentRoute $route): string => $route->config->accountId, $routes));

            return $routes[2];
        });
        $resolver = $this->resolver([$official, $aggregator], ['official_h5' => ['official-1', 'official-2'], 'aggregator_h5' => ['aggregator-1']], [$policy]);
        $route = $resolver->resolveMethod($this->app(), 'wallet', $this->context(), policyName: 'merchant');

        static::assertSame('aggregator', $route->config->provider->technicalName);
        static::assertSame('aggregator-1', $route->config->accountId);
        static::assertSame('fingerprint-aggregator-1', $route->config->accountFingerprint);
        static::assertSame('aggregator_h5', $route->config->product->technicalName);
    }

    public function testCustomSceneMethodAndInteractionNeedNoCoreSwitch(): void
    {
        $product = new PaymentProduct('plugin_terminal', 'plugin', 'terminal', ['terminal_intent'], 'custom_wallet', scenes: [new PaymentScene('terminal', 'kiosk', 'merchant_shell')], capabilities: [PaymentCapability::CAPTURE]);
        $context = new PaymentContext('EUR', new PaymentScene('terminal', 'kiosk', 'merchant_shell'), ['terminal_intent']);
        $route = $this->resolver([$product], ['plugin_terminal' => ['terminal-account']])->resolveMethod($this->app(), 'custom_wallet', $context);

        static::assertSame('terminal_intent', $route->interaction);
        static::assertSame('terminal_intent', $route->config->setting('flow'));
    }

    public function testSceneAndRuntimeIdentityAreCheckedBeforeResolvingCredentials(): void
    {
        $product = new PaymentProduct('plugin_mini', 'plugin', 'mini', [PaymentInteraction::SDK], 'wallet', scenes: [new PaymentScene('mini_program', 'mini', 'plugin')], runtimeParams: ['payer_id' => true], capabilities: [PaymentCapability::CAPTURE]);
        $config = static::createMock(PaymentConfigResolver::class);
        $config->expects($this->never())->method('resolveCandidates');
        $resolver = new PaymentRouteResolver($this->registry([$product]), new PaymentProductEligibility(), $config, [new DefaultPaymentRoutingPolicy()]);

        static::assertSame([], $resolver->options($this->app(), $this->context()));
        static::assertSame([], $resolver->options($this->app(), new PaymentContext('EUR', new PaymentScene('mini_program', 'mini', 'plugin'), [PaymentInteraction::SDK], ['plugin_mini'])));
        static::assertSame([], $resolver->options($this->app(), new PaymentContext('EUR', new PaymentScene('mini_program', 'mini', 'plugin'), [PaymentInteraction::SDK], ['other_mini'], ['plugin_mini' => ['payer_id' => 'payer']])));
    }

    public function testMethodAndProductAllowlistsApplyBeforePolicy(): void
    {
        $resolver = $this->resolver([$this->product('one', 'official'), $this->product('two', 'aggregator')], ['one' => ['a'], 'two' => ['b']]);
        $routes = $resolver->options($this->app(), $this->context(), ['two'], ['wallet']);

        static::assertCount(1, $routes);
        static::assertSame('two', $routes[0]->config->product->technicalName);
        static::assertSame([], $resolver->options($this->app(), $this->context(), allowedMethods: ['another_method']));
        $this->expectExceptionObject(PaymentProviderException::unknownChannel('one'));
        $resolver->resolveProduct($this->app(), 'one', $this->context(), ['two']);
    }

    public function testDisabledOfferingDoesNotHideOtherProviderOfSameMethod(): void
    {
        $products = [$this->product('disabled', 'official'), $this->product('available', 'aggregator')];
        $config = static::createStub(PaymentConfigResolver::class);
        $provider = $this->registry($products)->get('available');
        $config->method('resolveCandidates')->willReturnCallback(function (PaymentAppEntity $app, string $name) use ($provider): array {
            if ($name === 'disabled') {
                throw PaymentProviderException::unknownChannel($name);
            }

            return [new ResolvedPaymentConfig($this->provider('aggregator', [$provider]), $provider, [], [], [], [], 'account')];
        });
        $resolver = new PaymentRouteResolver($this->registry($products), new PaymentProductEligibility(), $config, [new DefaultPaymentRoutingPolicy()]);

        static::assertSame('available', $resolver->resolveMethod($this->app(), 'wallet', $this->context())->config->product->technicalName);
    }

    public function testExplicitProductPreservesTheAccountAdmissionError(): void
    {
        $product = $this->product('disabled', 'official');
        $config = static::createStub(PaymentConfigResolver::class);
        $rejection = PaymentProviderException::channelNotEnabled('disabled', 'app');
        $config->method('resolveCandidates')->willThrowException($rejection);
        $resolver = new PaymentRouteResolver($this->registry([$product]), new PaymentProductEligibility(), $config, [new DefaultPaymentRoutingPolicy()]);
        static::assertSame([], $resolver->options($this->app(), $this->context()));
        $this->expectExceptionObject($rejection);

        $resolver->resolveProduct($this->app(), 'disabled', $this->context());
    }

    public function testExplicitProductWithoutAnAuthorizedAccountIsForbidden(): void
    {
        $resolver = $this->resolver([$this->product('one', 'official')], ['one' => []]);
        static::assertSame([], $resolver->options($this->app(), $this->context()));
        $this->expectExceptionObject(PaymentProviderException::providerNotEnabled('official', 'app'));

        $resolver->resolveProduct($this->app(), 'one', $this->context());
    }

    public function testExplicitProductPreservesItsCurrencyRejection(): void
    {
        $product = new PaymentProduct('yuan', 'official', 'Yuan', [PaymentInteraction::REDIRECT], 'wallet', currencies: ['CNY'], capabilities: [PaymentCapability::CAPTURE]);
        $resolver = $this->resolver([$product], ['yuan' => ['account']]);
        static::assertSame([], $resolver->options($this->app(), $this->context()));
        $this->expectExceptionObject(PaymentProviderException::currencyNotSupported('yuan', 'EUR'));

        $resolver->resolveProduct($this->app(), 'yuan', $this->context());
    }

    public function testExplicitProductRejectsAnUnsupportedEnvironmentAsContextError(): void
    {
        $product = new PaymentProduct('mini', 'official', 'Mini', [PaymentInteraction::REDIRECT], 'wallet', scenes: [new PaymentScene('mini_program', 'mini', 'official')], capabilities: [PaymentCapability::CAPTURE]);
        $context = new PaymentContext('EUR', new PaymentScene('mini_program', 'mini', 'another-provider'));
        $resolver = $this->resolver([$product], ['mini' => ['account']]);
        static::assertSame([], $resolver->options($this->app(), $context));
        $this->expectExceptionObject(PaymentProviderException::contextNotSupported('mini', 'mini_program'));

        $resolver->resolveProduct($this->app(), 'mini', $context);
    }

    public function testPolicyCannotIntroduceUnauthorizedRoute(): void
    {
        $product = $this->product('one', 'official');
        $policy = static::createStub(PaymentRoutingPolicy::class);
        $policy->method('getName')->willReturn('invalid');
        $policy->method('choose')->willReturn(new PaymentRoute(new ResolvedPaymentConfig($this->provider('official', [$product]), $product, [], [], [], [], 'ungranted-account'), $this->context(), PaymentInteraction::REDIRECT));
        $resolver = $this->resolver([$product], ['one' => ['authorized-account']], [$policy]);

        $this->expectException(PaymentProviderException::class);
        $resolver->options($this->app(), $this->context(), policyName: 'invalid');
    }

    public function testProductWithoutCaptureIsNeverOffered(): void
    {
        $product = new PaymentProduct('read_only', 'official', 'read_only', [PaymentInteraction::REDIRECT], 'wallet', capabilities: [PaymentCapability::QUERY]);
        static::assertSame([], $this->resolver([$product], ['read_only' => ['account']])->options($this->app(), $this->context()));
    }

    /**
     * @param list<PaymentProduct> $products
     * @param array<string, list<string>> $accounts
     * @param list<PaymentRoutingPolicy> $policies
     */
    private function resolver(array $products, array $accounts, array $policies = []): PaymentRouteResolver
    {
        $registry = $this->registry($products);
        $config = static::createStub(PaymentConfigResolver::class);
        $config->method('resolveCandidates')->willReturnCallback(function (PaymentAppEntity $app, string $name) use ($registry, $accounts): array {
            $product = $registry->get($name);
            $provider = $this->provider($product->providerTechnicalName, [$product]);

            return array_map(static fn (string $account): ResolvedPaymentConfig => new ResolvedPaymentConfig($provider, $product, [], [], [], [], $account, accountFingerprint: 'fingerprint-' . $account), $accounts[$name]);
        });

        return new PaymentRouteResolver($registry, new PaymentProductEligibility(), $config, $policies ?: [new DefaultPaymentRoutingPolicy()]);
    }

    /**
     * @param list<PaymentProduct> $products
     */
    private function registry(array $products): PaymentProductRegistry
    {
        $grouped = [];
        foreach ($products as $product) {
            $grouped[$product->providerTechnicalName][] = $product;
        }
        $providers = [];
        foreach ($grouped as $name => $offerings) {
            $providers[] = $this->provider($name, $offerings);
        }

        return new PaymentProductRegistry(new PaymentProviderRegistry($providers));
    }

    /**
     * @param list<PaymentProduct> $products
     */
    private function provider(string $name, array $products): PaymentProvider
    {
        return new PaymentProvider($name, $name, [], array_combine(array_map(static fn (PaymentProduct $product): string => $product->technicalName, $products), $products), static::createStub(PaymentGatewayFactory::class), static::createStub(PaymentNotificationExtractorInterface::class));
    }

    private function product(string $name, string $provider): PaymentProduct
    {
        return new PaymentProduct($name, $provider, $name, [PaymentInteraction::REDIRECT], 'wallet', scenes: [new PaymentScene('browser', 'wap')], capabilities: [PaymentCapability::CAPTURE]);
    }

    private function app(): PaymentAppEntity
    {
        $app = new PaymentAppEntity();
        $app->id = 'app';

        return $app;
    }

    private function context(): PaymentContext
    {
        return new PaymentContext('EUR', new PaymentScene('browser', 'wap', 'browser'));
    }
}
