<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Wallet\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Wallet\Event\WalletTopUpCreditedEvent;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(WalletTopUpCreditedEvent::class)]
class WalletTopUpCreditedEventTest extends TestCase
{
    public function testAccessors(): void
    {
        $context = Context::createDefaultContext();

        $event = new WalletTopUpCreditedEvent('top-up-id', 'customer-id', 'CNY', 128.0, $context);

        static::assertSame('top-up-id', $event->getTopUpId());
        static::assertSame('customer-id', $event->getCustomerId());
        static::assertSame('CNY', $event->getCurrency());
        static::assertSame(128.0, $event->getAmount());
        static::assertSame($context, $event->getContext());
    }
}
