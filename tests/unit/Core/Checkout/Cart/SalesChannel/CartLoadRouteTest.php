<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Cart\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Cart\CartCalculator;
use Shopwell\Core\Checkout\Cart\Extension\CartLoadRouteExtension;
use Shopwell\Core\Checkout\Cart\SalesChannel\CartLoadRoute;
use Shopwell\Core\Checkout\Cart\SalesChannel\CartResponse;
use Shopwell\Core\Checkout\Cart\TaxProvider\TaxProviderProcessor;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CartLoadRoute::class)]
class CartLoadRouteTest extends TestCase
{
    public function testLoadCalculatesTheCartOfTheContextToken(): void
    {
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext
            ->expects($this->once())
            ->method('getToken')
            ->willReturn('test');

        $calculatedCart = new Cart('test');
        $calculator = $this->createMock(CartCalculator::class);
        $calculator
            ->expects($this->once())
            ->method('calculateByToken')
            ->with('test', $salesChannelContext)
            ->willReturn($calculatedCart);

        $cartLoadRoute = new CartLoadRoute($calculator, static::createStub(TaxProviderProcessor::class), new ExtensionDispatcher(new EventDispatcher()));

        static::assertSame($calculatedCart, $cartLoadRoute->load(new Request(), $salesChannelContext)->getCart());
    }

    public function testLoadReusesTheResolvedCart(): void
    {
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext
            ->expects($this->once())
            ->method('getToken')
            ->willReturn('test');

        $resolvedCart = new Cart('test');

        $calculator = $this->createMock(CartCalculator::class);
        $calculator->expects($this->never())->method('calculateByToken');

        $cartLoadRoute = new CartLoadRoute($calculator, static::createStub(TaxProviderProcessor::class), new ExtensionDispatcher(new EventDispatcher()));

        static::assertSame(
            $resolvedCart,
            $cartLoadRoute->load(new Request(), $salesChannelContext, $resolvedCart)->getCart()
        );
    }

    public function testLoadCalculatesAnotherTokenFromScratch(): void
    {
        $salesChannelContext = static::createStub(SalesChannelContext::class);
        $salesChannelContext
            ->method('getToken')
            ->willReturn('context-token');

        $calculatedCart = new Cart('other-token');
        $calculator = $this->createMock(CartCalculator::class);
        $calculator
            ->expects($this->once())
            ->method('calculateByToken')
            ->with('other-token', $salesChannelContext)
            ->willReturn($calculatedCart);

        $cartLoadRoute = new CartLoadRoute($calculator, static::createStub(TaxProviderProcessor::class), new ExtensionDispatcher(new EventDispatcher()));

        $request = new Request(['token' => 'other-token']);

        static::assertSame(
            $calculatedCart,
            $cartLoadRoute->load($request, $salesChannelContext, new Cart('context-token'))->getCart()
        );
    }

    public function testPublishesExtension(): void
    {
        $request = new Request();
        $context = Generator::generateSalesChannelContext();
        $cart = new Cart(Uuid::randomHex());
        $response = new CartResponse(new Cart('token'));

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('cart-load-route.load.pre', static function (CartLoadRouteExtension $extension) use ($request, $context, $cart, $response): void {
            static::assertSame(['request' => $request, 'context' => $context, 'cart' => $cart], $extension->getParams());

            $extension->result = $response;
            $extension->stopPropagation();
        });

        $route = new CartLoadRoute(
            static::createStub(CartCalculator::class),
            static::createStub(TaxProviderProcessor::class),
            new ExtensionDispatcher($dispatcher),
        );

        static::assertSame($response, $route->load($request, $context, $cart));
    }
}
