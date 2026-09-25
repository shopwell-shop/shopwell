<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Rule\Rule\LineItem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Cart\LineItem\LineItem;
use Shopwell\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopwell\Core\Checkout\Cart\Rule\LineItemOfTypeRule;
use Shopwell\Core\Checkout\Cart\Rule\LineItemScope;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(LineItemOfTypeRule::class)]
class LineItemOfTypeRuleTest extends TestCase
{
    public function testRuleWithProductTypeMatch(): void
    {
        $rule = (new LineItemOfTypeRule())->assign(['lineItemType' => LineItem::PRODUCT_LINE_ITEM_TYPE]);

        $context = static::createStub(SalesChannelContext::class);

        static::assertTrue(
            $rule->match(new LineItemScope(new LineItem('A', 'product'), $context))
        );

        $cart = new Cart('test');
        $cart->add(new LineItem('A', 'product'));

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testRuleWithProductTypeNotMatch(): void
    {
        $rule = (new LineItemOfTypeRule())->assign(['lineItemType' => 'voucher']);

        $context = static::createStub(SalesChannelContext::class);

        static::assertFalse(
            $rule->match(new LineItemScope(new LineItem('A', 'product'), $context))
        );

        $cart = new Cart('test');
        $cart->add(new LineItem('A', 'product'));

        $scope = new CartRuleScope($cart, $context);

        static::assertFalse($rule->match($scope));
    }
}
