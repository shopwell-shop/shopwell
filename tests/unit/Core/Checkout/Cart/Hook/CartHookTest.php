<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Cart\Hook;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Cart\Hook\CartHook;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CartHook::class)]
class CartHookTest extends TestCase
{
    public function testNameRespectsCartSource(): void
    {
        $cart = new Cart('test');
        $cart->setSource('test');
        $hook = new CartHook($cart, static::createStub(SalesChannelContext::class));

        static::assertSame('cart-test', $hook->getName());
    }

    public function testNameWithoutCartSource(): void
    {
        $cart = new Cart('test');
        $hook = new CartHook($cart, static::createStub(SalesChannelContext::class));

        static::assertSame('cart', $hook->getName());
    }
}
