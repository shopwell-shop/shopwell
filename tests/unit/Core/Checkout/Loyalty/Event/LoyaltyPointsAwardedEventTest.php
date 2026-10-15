<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Loyalty\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Loyalty\Event\LoyaltyPointsAwardedEvent;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(LoyaltyPointsAwardedEvent::class)]
class LoyaltyPointsAwardedEventTest extends TestCase
{
    public function testAccessors(): void
    {
        $context = Context::createDefaultContext();

        $event = new LoyaltyPointsAwardedEvent('customer-id', 100, 'order-id', $context);

        static::assertSame('customer-id', $event->getCustomerId());
        static::assertSame(100, $event->getPoints());
        static::assertSame('order-id', $event->getOrderId());
        static::assertSame($context, $event->getContext());
    }

    public function testOrderIdCanBeNull(): void
    {
        $event = new LoyaltyPointsAwardedEvent('customer-id', 100, null, Context::createDefaultContext());

        static::assertNull($event->getOrderId());
    }
}
