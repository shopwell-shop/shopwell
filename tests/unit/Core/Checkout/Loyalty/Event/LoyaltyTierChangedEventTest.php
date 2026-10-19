<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Loyalty\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Loyalty\Event\LoyaltyTierChangedEvent;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(LoyaltyTierChangedEvent::class)]
class LoyaltyTierChangedEventTest extends TestCase
{
    public function testAccessors(): void
    {
        $context = Context::createDefaultContext();

        $event = new LoyaltyTierChangedEvent('customer-id', 'old-tier', 'new-tier', $context);

        static::assertSame('customer-id', $event->getCustomerId());
        static::assertSame('old-tier', $event->getPreviousTierId());
        static::assertSame('new-tier', $event->getNewTierId());
        static::assertSame($context, $event->getContext());
    }

    public function testTierIdsCanBeNull(): void
    {
        $event = new LoyaltyTierChangedEvent('customer-id', null, null, Context::createDefaultContext());

        static::assertNull($event->getPreviousTierId());
        static::assertNull($event->getNewTierId());
    }
}
