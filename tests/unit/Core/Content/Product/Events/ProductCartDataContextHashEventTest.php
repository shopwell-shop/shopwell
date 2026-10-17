<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Product\Events;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Cart\CartBehavior;
use Shopwell\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopwell\Core\Content\Product\Events\ProductCartDataContextHashEvent;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Test\Generator;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductCartDataContextHashEvent::class)]
class ProductCartDataContextHashEventTest extends TestCase
{
    public function testExposesTheContext(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();

        $data = new CartDataCollection();
        $cart = new Cart('test');
        $behavior = new CartBehavior();

        $event = new ProductCartDataContextHashEvent($data, $cart, $salesChannelContext, $behavior);

        static::assertSame($data, $event->getData());
        static::assertSame($cart, $event->getOriginalCart());
        static::assertSame($behavior, $event->getBehavior());
        static::assertSame($salesChannelContext, $event->getSalesChannelContext());
        static::assertSame($salesChannelContext->getContext(), $event->getContext());
        static::assertSame([], $event->getParts());
    }

    public function testPartsAreSortedByNameAndOverwritten(): void
    {
        $event = new ProductCartDataContextHashEvent(new CartDataCollection(), new Cart('test'), Generator::generateSalesChannelContext(), new CartBehavior());

        $event->addPart('b', 'second');
        $event->addPart('a', ['first']);
        $event->addPart('b', 2);

        static::assertSame(['a' => ['first'], 'b' => 2], $event->getParts());
    }
}
