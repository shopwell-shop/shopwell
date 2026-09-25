<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Cart\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Cart\Event\BeforeCartMergeEvent;
use Shopwell\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(BeforeCartMergeEvent::class)]
class BeforeCartMergeEventTest extends TestCase
{
    public function testReturnsCorrectProperties(): void
    {
        $customerCart = new Cart('customerCart');
        $guestCart = new Cart('customerCart');
        $mergeableLineItems = new LineItemCollection();
        $salesChannelContext = static::createStub(SalesChannelContext::class);

        $context = Context::createDefaultContext();
        $salesChannelContext->method('getContext')->willReturn($context);

        $event = new BeforeCartMergeEvent(
            $customerCart,
            $guestCart,
            $mergeableLineItems,
            $salesChannelContext
        );

        static::assertSame($customerCart, $event->getCustomerCart());
        static::assertSame($guestCart, $event->getGuestCart());
        static::assertSame($mergeableLineItems, $event->getMergeableLineItems());
        static::assertSame($salesChannelContext, $event->getSalesChannelContext());
        static::assertSame($context, $event->getContext());
    }
}
