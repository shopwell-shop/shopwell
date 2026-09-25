<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Storefront\Page\Checkout;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Cart\Error\ErrorCollection;
use Shopwell\Core\Checkout\Gateway\SalesChannel\CheckoutGatewayRouteResponse;
use Shopwell\Core\Checkout\Payment\PaymentMethodCollection;
use Shopwell\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopwell\Core\Framework\Adapter\Translation\Translator;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\System\Country\SalesChannel\CountryRoute;
use Shopwell\Storefront\Checkout\Cart\SalesChannel\StorefrontCartFacade;
use Shopwell\Storefront\Checkout\Cart\SalesChannel\StorefrontCartGatewayResult;
use Shopwell\Storefront\Page\Checkout\Cart\CheckoutCartPageLoadedEvent;
use Shopwell\Storefront\Page\Checkout\Cart\CheckoutCartPageLoader;
use Shopwell\Storefront\Page\GenericPageLoader;
use Shopwell\Storefront\Test\Page\StorefrontPageTestBehaviour;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('checkout')]
class CartPageTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontPageTestBehaviour;

    public function testItLoadsTheCart(): void
    {
        $request = new Request();
        $context = $this->createSalesChannelContextWithNavigation();

        $event = null;
        $this->catchEvent(CheckoutCartPageLoadedEvent::class, $event);

        $page = $this->getPageLoader()->load($request, $context);

        static::assertSame(0.0, $page->getCart()->getPrice()->getNetPrice());
        static::assertSame($context->getToken(), $page->getCart()->getToken());
        self::assertPageEvent(CheckoutCartPageLoadedEvent::class, $event, $context, $request, $page);
    }

    public function testAddsCurrentSelectedShippingMethod(): void
    {
        $response = new CheckoutGatewayRouteResponse(
            new PaymentMethodCollection(),
            new ShippingMethodCollection(),
            new ErrorCollection()
        );

        $context = $this->createSalesChannelContextWithNavigation();

        $cartService = static::createStub(StorefrontCartFacade::class);
        $cartService
            ->method('getWithCheckoutGateway')
            ->willReturn(new StorefrontCartGatewayResult(new Cart($context->getToken()), $response));

        $loader = new CheckoutCartPageLoader(
            static::getContainer()->get(GenericPageLoader::class),
            static::getContainer()->get('event_dispatcher'),
            $cartService,
            static::getContainer()->get(CountryRoute::class),
            static::getContainer()->get(Translator::class)
        );

        $result = $loader->load(new Request(), $context);

        static::assertTrue($result->getShippingMethods()->has($context->getShippingMethod()->getId()));
    }

    protected function getPageLoader(): CheckoutCartPageLoader
    {
        return static::getContainer()->get(CheckoutCartPageLoader::class);
    }
}
