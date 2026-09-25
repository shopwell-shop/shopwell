<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Cart\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\Event\SalesChannelContextAssembledEvent;
use Shopwell\Core\Checkout\Order\OrderEntity;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Test\Generator;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(SalesChannelContextAssembledEvent::class)]
class SalesChannelContextAssembledEventTest extends TestCase
{
    public function testConstruct(): void
    {
        $order = new OrderEntity();
        $context = Generator::generateSalesChannelContext();

        $event = new SalesChannelContextAssembledEvent($order, $context);

        static::assertSame($order, $event->getOrder());
        static::assertSame($context->getContext(), $event->getContext());
    }
}
