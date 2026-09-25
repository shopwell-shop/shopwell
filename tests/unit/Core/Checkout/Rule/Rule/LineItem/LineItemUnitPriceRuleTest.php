<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Rule\Rule\LineItem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Cart\LineItem\LineItem;
use Shopwell\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopwell\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopwell\Core\Checkout\Cart\Rule\LineItemScope;
use Shopwell\Core\Checkout\Cart\Rule\LineItemUnitPriceRule;
use Shopwell\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopwell\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Rule\Rule;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(LineItemUnitPriceRule::class)]
class LineItemUnitPriceRuleTest extends TestCase
{
    private LineItem $lineItem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->lineItem = (new LineItem('A', 'product'))
            ->setPrice(
                new CalculatedPrice(100, 200, new CalculatedTaxCollection(), new TaxRuleCollection())
            );
    }

    public function testRuleWithExactAmountMatch(): void
    {
        $rule = (new LineItemUnitPriceRule())->assign(['amount' => 100, 'operator' => Rule::OPERATOR_EQ]);

        $context = static::createStub(SalesChannelContext::class);

        static::assertTrue(
            $rule->match(new LineItemScope($this->lineItem, $context))
        );

        $cart = new Cart('test');
        $cart->add($this->lineItem);
        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testRuleWithExactAmountNotMatch(): void
    {
        $rule = (new LineItemUnitPriceRule())->assign(['amount' => 99, 'operator' => Rule::OPERATOR_EQ]);

        $context = static::createStub(SalesChannelContext::class);

        static::assertFalse(
            $rule->match(new LineItemScope($this->lineItem, $context))
        );

        $cart = new Cart('test');
        $cart->add($this->lineItem);
        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testRuleWithLowerThanEqualExactAmountMatch(): void
    {
        $rule = (new LineItemUnitPriceRule())->assign(['amount' => 100, 'operator' => Rule::OPERATOR_LTE]);

        $context = static::createStub(SalesChannelContext::class);

        static::assertTrue(
            $rule->match(new LineItemScope($this->lineItem, $context))
        );

        $cart = new Cart('test');
        $cart->add($this->lineItem);
        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testRuleWithLowerThanEqualAmountMatch(): void
    {
        $rule = (new LineItemUnitPriceRule())->assign(['amount' => 101, 'operator' => Rule::OPERATOR_LTE]);

        $context = static::createStub(SalesChannelContext::class);

        static::assertTrue(
            $rule->match(new LineItemScope($this->lineItem, $context))
        );

        $cart = new Cart('test');
        $cart->add($this->lineItem);
        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testRuleWithLowerThanEqualAmountNotMatch(): void
    {
        $rule = (new LineItemUnitPriceRule())->assign(['amount' => 99, 'operator' => Rule::OPERATOR_LTE]);

        $context = static::createStub(SalesChannelContext::class);

        static::assertFalse(
            $rule->match(new LineItemScope($this->lineItem, $context))
        );

        $cart = new Cart('test');
        $cart->add($this->lineItem);
        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testRuleWithGreaterThanEqualExactAmountMatch(): void
    {
        $rule = (new LineItemUnitPriceRule())->assign(['amount' => 100, 'operator' => Rule::OPERATOR_GTE]);

        $context = static::createStub(SalesChannelContext::class);

        static::assertTrue(
            $rule->match(new LineItemScope($this->lineItem, $context))
        );

        $cart = new Cart('test');
        $cart->add($this->lineItem);
        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testRuleWithGreaterThanEqualMatch(): void
    {
        $rule = (new LineItemUnitPriceRule())->assign(['amount' => 99, 'operator' => Rule::OPERATOR_GTE]);

        $context = static::createStub(SalesChannelContext::class);

        static::assertTrue(
            $rule->match(new LineItemScope($this->lineItem, $context))
        );

        $cart = new Cart('test');
        $cart->add($this->lineItem);
        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testRuleWithGreaterThanEqualNotMatch(): void
    {
        $rule = (new LineItemUnitPriceRule())->assign(['amount' => 101, 'operator' => Rule::OPERATOR_GTE]);

        $context = static::createStub(SalesChannelContext::class);

        static::assertFalse(
            $rule->match(new LineItemScope($this->lineItem, $context))
        );

        $cart = new Cart('test');
        $cart->add($this->lineItem);
        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testRuleWithNotEqualMatch(): void
    {
        $rule = (new LineItemUnitPriceRule())->assign(['amount' => 101, 'operator' => Rule::OPERATOR_NEQ]);

        $context = static::createStub(SalesChannelContext::class);

        static::assertTrue(
            $rule->match(new LineItemScope($this->lineItem, $context))
        );

        $cart = new Cart('test');
        $cart->add($this->lineItem);
        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testRuleWithNotEqualNotMatch(): void
    {
        $rule = (new LineItemUnitPriceRule())->assign(['amount' => 100, 'operator' => Rule::OPERATOR_NEQ]);

        $context = static::createStub(SalesChannelContext::class);

        static::assertFalse(
            $rule->match(new LineItemScope($this->lineItem, $context))
        );

        $cart = new Cart('test');
        $cart->add($this->lineItem);
        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }
}
