<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Loyalty\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Loyalty\Event\LoyaltyPointsRedeemedEvent;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(LoyaltyPointsRedeemedEvent::class)]
class LoyaltyPointsRedeemedEventTest extends TestCase
{
    public function testAccessors(): void
    {
        $context = Context::createDefaultContext();

        $event = new LoyaltyPointsRedeemedEvent('customer-id', 75, 'order-id', $context);

        static::assertSame('customer-id', $event->getCustomerId());
        static::assertSame(75, $event->getPoints());
        static::assertSame('order-id', $event->getOrderId());
        static::assertSame($context, $event->getContext());
    }

    public function testOrderIdCanBeNull(): void
    {
        $event = new LoyaltyPointsRedeemedEvent('customer-id', 75, null, Context::createDefaultContext());

        static::assertNull($event->getOrderId());
    }
}
