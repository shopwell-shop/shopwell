<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Payment\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Payment\Aggregate\PaymentMethodGatewayChannel\PaymentMethodGatewayChannelEntity;
use Shopwell\Core\Checkout\Payment\Extension\PaymentMethodRouteExtension;
use Shopwell\Core\Checkout\Payment\Gateway\PaymentGatewayHandler;
use Shopwell\Core\Checkout\Payment\Gateway\PaymentMethodCollectability;
use Shopwell\Core\Checkout\Payment\PaymentMethodCollection;
use Shopwell\Core\Checkout\Payment\PaymentMethodEntity;
use Shopwell\Core\Checkout\Payment\SalesChannel\PaymentMethodRoute;
use Shopwell\Core\Checkout\Payment\SalesChannel\PaymentMethodRouteResponse;
use Shopwell\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Rule\RuleIdMatcher;
use Shopwell\Core\Framework\Script\Execution\ScriptExecutor;
use Shopwell\Core\System\Currency\CurrencyEntity;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentChannel;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentChannelRegistry;
use Shopwell\Core\System\Payment\Provider\PaymentProviderRegistry;
use Shopwell\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\Test\Generator;
use Shopwell\Tests\Unit\Core\Checkout\Payment\Gateway\PaymentGatewayFixture;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(PaymentMethodRoute::class)]
class PaymentMethodRouteTest extends TestCase
{
    public function testPublishesExtension(): void
    {
        $request = new Request();
        $context = Generator::generateSalesChannelContext();
        $criteria = new Criteria();
        $response = static::createStub(PaymentMethodRouteResponse::class);

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('payment-method-route.load.pre', static function (PaymentMethodRouteExtension $extension) use ($request, $context, $criteria, $response): void {
            static::assertSame(['request' => $request, 'context' => $context, 'criteria' => $criteria], $extension->getParams());

            $extension->result = $response;
            $extension->stopPropagation();
        });

        $route = $this->route(new ExtensionDispatcher($dispatcher), $this->paymentMethods());

        static::assertSame($response, $route->load($request, $context, $criteria));
    }

    /**
     * A payment method that collects through the gateway without a channel is removed from the result
     * even though the caller did not ask for `onlyAvailable`: it would throw at the end of the
     * checkout, and offering it is not a merchandising decision.
     */
    public function testAFilterRemovesPaymentMethodsThatCannotCollect(): void
    {
        $request = new Request();

        $route = $this->route(new ExtensionDispatcher(new EventDispatcher()), $this->paymentMethods());

        $response = $route->load($request, $this->context(), new Criteria());

        static::assertSame(['invoice-id'], array_values($response->getPaymentMethods()->getIds()));
    }

    /**
     * The `device` the storefront asks for reaches the filter: a channel that is declared for `wap`
     * only is not offered to a desktop request, while a request that names no device keeps it.
     */
    public function testTheDeviceOfTheRequestDecidesWhichChannelIsOffered(): void
    {
        $desktop = $this->route(
            new ExtensionDispatcher(new EventDispatcher()),
            $this->paymentMethods(),
            $this->wapOnlyCollectability()
        );
        $response = $desktop->load(new Request(['device' => 'web']), $this->context(), new Criteria());

        static::assertSame(['invoice-id'], array_values($response->getPaymentMethods()->getIds()));

        $silent = $this->route(
            new ExtensionDispatcher(new EventDispatcher()),
            $this->paymentMethods(),
            $this->wapOnlyCollectability()
        );
        $response = $silent->load(new Request(), $this->context(), new Criteria());

        static::assertSame(['invoice-id', 'gateway-id'], array_values($response->getPaymentMethods()->getIds()));
    }

    /**
     * @param list<PaymentMethodEntity> $methods
     */
    private function route(
        ExtensionDispatcher $dispatcher,
        array $methods,
        ?PaymentMethodCollectability $collectability = null,
    ): PaymentMethodRoute {
        $repository = static::createMock(SalesChannelRepository::class);
        $repository->expects(static::any())->method('search')->willReturn(new EntitySearchResult(
            'payment_method',
            \count($methods),
            new PaymentMethodCollection($methods),
            null,
            new Criteria(),
            Context::createDefaultContext()
        ));

        return new PaymentMethodRoute(
            $repository,
            static::createStub(CacheTagCollector::class),
            static::createStub(ScriptExecutor::class),
            static::createStub(RuleIdMatcher::class),
            $dispatcher,
            $collectability ?? $this->collectability(),
        );
    }

    /**
     * A method that collects through an interface the shop has an account for, on a channel declared for
     * phones only - the constellation in which the device of the request is the deciding dimension.
     */
    private function wapOnlyCollectability(): PaymentMethodCollectability
    {
        $binding = new PaymentMethodGatewayChannelEntity();
        $binding->id = 'binding-of-gateway-id';
        $binding->setUniqueIdentifier($binding->id);
        $binding->paymentMethodId = 'gateway-id';
        $binding->paymentAppId = PaymentGatewayFixture::PLATFORM_APP_ID;
        $binding->channelTechnicalName = PaymentGatewayFixture::CHANNEL;

        return new PaymentMethodCollectability(
            PaymentGatewayFixture::repository([$binding]),
            PaymentGatewayFixture::repository([PaymentGatewayFixture::channelConfig()]),
            PaymentGatewayFixture::repository([PaymentGatewayFixture::providerConfig()]),
            PaymentGatewayFixture::repository([PaymentGatewayFixture::platformAppEntity()]),
            PaymentGatewayFixture::repository(),
            PaymentGatewayFixture::channelRegistry(devices: [PaymentChannel::DEVICE_WAP]),
        );
    }

    private function collectability(): PaymentMethodCollectability
    {
        return new PaymentMethodCollectability(
            static::createStub(EntityRepository::class),
            static::createStub(EntityRepository::class),
            static::createStub(EntityRepository::class),
            static::createStub(EntityRepository::class),
            static::createStub(EntityRepository::class),
            new PaymentChannelRegistry(new PaymentProviderRegistry([])),
        );
    }

    /**
     * The route reads the currency of the shopping context, so the context of the test carries one.
     */
    private function context(): SalesChannelContext
    {
        $currency = (new CurrencyEntity())->assign([
            'id' => 'currency-id',
            'isoCode' => 'EUR',
            'factor' => 1.0,
            'symbol' => '€',
            'position' => 1,
        ]);

        return Generator::generateSalesChannelContext(currency: $currency);
    }

    /**
     * @return list<PaymentMethodEntity>
     */
    private function paymentMethods(): array
    {
        return [
            (new PaymentMethodEntity())->assign([
                'id' => 'invoice-id',
                'handlerIdentifier' => 'Shopwell\Core\Checkout\Payment\Cart\PaymentHandler\InvoicePayment',
                'name' => 'Invoice',
                'position' => 1,
            ]),
            (new PaymentMethodEntity())->assign([
                'id' => 'gateway-id',
                'handlerIdentifier' => PaymentGatewayHandler::class,
                'name' => 'Alipay',
                'position' => 2,
            ]),
        ];
    }
}
