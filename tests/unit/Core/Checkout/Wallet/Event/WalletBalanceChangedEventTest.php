<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Wallet\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Wallet\Event\WalletBalanceChangedEvent;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(WalletBalanceChangedEvent::class)]
class WalletBalanceChangedEventTest extends TestCase
{
    public function testAccessors(): void
    {
        $context = Context::createDefaultContext();

        $event = new WalletBalanceChangedEvent('customer-id', 'currency-id', 'top_up', 25.0, 125.0, $context);

        static::assertSame('customer-id', $event->getCustomerId());
        static::assertSame('currency-id', $event->getCurrencyId());
        static::assertSame('top_up', $event->getType());
        static::assertSame(25.0, $event->getAmount());
        static::assertSame(125.0, $event->getBalanceAfter());
        static::assertSame($context, $event->getContext());
    }
}
