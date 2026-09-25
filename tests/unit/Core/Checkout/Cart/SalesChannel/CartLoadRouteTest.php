<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Cart\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Cart\CartCalculator;
use Shopwell\Core\Checkout\Cart\SalesChannel\CartLoadRoute;
use Shopwell\Core\Checkout\Cart\TaxProvider\TaxProviderProcessor;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
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

        $cartLoadRoute = new CartLoadRoute($calculator, static::createStub(TaxProviderProcessor::class));

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

        $cartLoadRoute = new CartLoadRoute($calculator, static::createStub(TaxProviderProcessor::class));

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

        $cartLoadRoute = new CartLoadRoute($calculator, static::createStub(TaxProviderProcessor::class));

        $request = new Request(['token' => 'other-token']);

        static::assertSame(
            $calculatedCart,
            $cartLoadRoute->load($request, $salesChannelContext, new Cart('context-token'))->getCart()
        );
    }
}
