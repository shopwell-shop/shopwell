<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Cart\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\AbstractCartPersister;
use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Cart\CartCalculator;
use Shopwell\Core\Checkout\Cart\CartLocker;
use Shopwell\Core\Checkout\Cart\Extension\CartItemUpdateRouteExtension;
use Shopwell\Core\Checkout\Cart\LineItemFactoryRegistry;
use Shopwell\Core\Checkout\Cart\SalesChannel\CartItemUpdateRoute;
use Shopwell\Core\Checkout\Cart\SalesChannel\CartResponse;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CartItemUpdateRoute::class)]
class CartItemUpdateRouteTest extends TestCase
{
    public function testRouteUsesLock(): void
    {
        $cartLocker = $this->createMock(CartLocker::class);
        $cartLocker
            ->expects($this->once())
            ->method('locked')
            ->willReturnCallback(static fn (SalesChannelContext $context, \Closure $closure) => $closure());

        $lineItemFactory = $this->createMock(LineItemFactoryRegistry::class);
        $lineItemFactory
            ->expects($this->once())
            ->method('update');

        $route = new CartItemUpdateRoute(
            static::createStub(AbstractCartPersister::class),
            static::createStub(CartCalculator::class),
            $lineItemFactory,
            static::createStub(EventDispatcherInterface::class),
            $cartLocker,
            new ExtensionDispatcher(new EventDispatcher())
        );

        $route->change(
            new Request([], ['items' => [['id' => 'test', 'quantity' => 2]]]),
            new Cart('test'),
            static::createStub(SalesChannelContext::class)
        );
    }

    public function testPublishesExtension(): void
    {
        $request = new Request();
        $cart = new Cart(Uuid::randomHex());
        $context = Generator::generateSalesChannelContext();
        $response = new CartResponse(new Cart('token'));

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('cart-item-update-route.change.pre', static function (CartItemUpdateRouteExtension $extension) use ($request, $cart, $context, $response): void {
            static::assertSame(['request' => $request, 'cart' => $cart, 'context' => $context], $extension->getParams());

            $extension->result = $response;
            $extension->stopPropagation();
        });

        $route = new CartItemUpdateRoute(
            static::createStub(AbstractCartPersister::class),
            static::createStub(CartCalculator::class),
            static::createStub(LineItemFactoryRegistry::class),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(CartLocker::class),
            new ExtensionDispatcher($dispatcher),
        );

        static::assertSame($response, $route->change($request, $cart, $context));
    }
}
