<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Rule\Rule\LineItem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Cart\LineItem\LineItem;
use Shopwell\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopwell\Core\Checkout\Cart\Rule\LineItemRule;
use Shopwell\Core\Checkout\Cart\Rule\LineItemScope;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(LineItemRule::class)]
class LineItemRuleTest extends TestCase
{
    public function testRuleMatch(): void
    {
        $rule = (new LineItemRule())
            ->assign(['identifiers' => ['A']]);

        $context = static::createStub(SalesChannelContext::class);

        $lineItem = new LineItem('A', 'product', 'A');

        static::assertTrue(
            $rule->match(new LineItemScope($lineItem, $context))
        );

        $cart = new Cart('test');
        $cart->add($lineItem);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testRuleNotMatch(): void
    {
        $rule = (new LineItemRule())
            ->assign(['identifiers' => ['A']]);

        $context = static::createStub(SalesChannelContext::class);

        $lineItem = new LineItem('A', 'product', 'B');

        static::assertFalse(
            $rule->match(new LineItemScope($lineItem, $context))
        );

        $cart = new Cart('test');
        $cart->add($lineItem);

        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }
}
