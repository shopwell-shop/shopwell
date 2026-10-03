<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Cart\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\AbstractCartPersister;
use Shopwell\Core\Checkout\Cart\CartLocker;
use Shopwell\Core\Checkout\Cart\Extension\CartDeleteRouteExtension;
use Shopwell\Core\Checkout\Cart\SalesChannel\CartDeleteRoute;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\NoContentResponse;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CartDeleteRoute::class)]
class CartDeleteRouteTest extends TestCase
{
    public function testRouteUsesLock(): void
    {
        $cartLocker = $this->createMock(CartLocker::class);
        $cartLocker
            ->expects($this->once())
            ->method('locked')
            ->willReturnCallback(static fn (SalesChannelContext $context, \Closure $closure) => $closure());

        $persister = $this->createMock(AbstractCartPersister::class);
        $persister
            ->expects($this->once())
            ->method('delete');

        $route = new CartDeleteRoute(
            $persister,
            static::createStub(EventDispatcherInterface::class),
            $cartLocker,
            new ExtensionDispatcher(new EventDispatcher())
        );

        $route->delete(
            static::createStub(SalesChannelContext::class)
        );
    }

    public function testPublishesExtension(): void
    {
        $context = Generator::generateSalesChannelContext();
        $response = new NoContentResponse();

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('cart-delete-route.delete.pre', static function (CartDeleteRouteExtension $extension) use ($context, $response): void {
            static::assertSame(['context' => $context], $extension->getParams());

            $extension->result = $response;
            $extension->stopPropagation();
        });

        $route = new CartDeleteRoute(
            static::createStub(AbstractCartPersister::class),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(CartLocker::class),
            new ExtensionDispatcher($dispatcher),
        );

        static::assertSame($response, $route->delete($context));
    }
}
