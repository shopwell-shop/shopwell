<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Rule\Rule\Cart;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Cart\LineItem\LineItem;
use Shopwell\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopwell\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopwell\Core\Checkout\Cart\Rule\GoodsPriceRule;
use Shopwell\Core\Checkout\Cart\Rule\LineItemScope;
use Shopwell\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopwell\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Rule\Rule;
use Shopwell\Core\Framework\Rule\RuleScope;
use Shopwell\Core\Framework\Rule\SimpleRule;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\Test\Checkout\CartRuleFixture;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(GoodsPriceRule::class)]
class GoodsPriceRuleTest extends TestCase
{
    public function testRuleWithExactPriceMatch(): void
    {
        $rule = (new GoodsPriceRule())->assign(['amount' => 270.0, 'operator' => Rule::OPERATOR_EQ]);

        $cart = new Cart('test');
        $cart->add(
            (new LineItem('a', 'a'))
                ->setPrice(new CalculatedPrice(270, 270, new CalculatedTaxCollection(), new TaxRuleCollection()))
        );

        $context = static::createStub(SalesChannelContext::class);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testRuleWithExactPriceNotMatch(): void
    {
        $rule = (new GoodsPriceRule())->assign(['amount' => 1.0, 'operator' => Rule::OPERATOR_EQ]);

        $cart = new Cart('test');
        $context = static::createStub(SalesChannelContext::class);

        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testRuleWithLowerThanEqualExactPriceMatch(): void
    {
        $rule = (new GoodsPriceRule())->assign(['amount' => 270.0, 'operator' => Rule::OPERATOR_LTE]);

        $cart = new Cart('test');
        $context = static::createStub(SalesChannelContext::class);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testRuleWithLowerThanEqualPriceMatch(): void
    {
        $rule = (new GoodsPriceRule())->assign(['amount' => 300.0, 'operator' => Rule::OPERATOR_LTE]);

        $cart = new Cart('test');
        $context = static::createStub(SalesChannelContext::class);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testRuleWithLowerThanEqualPriceNotMatch(): void
    {
        $rule = (new GoodsPriceRule())->assign(['amount' => -1.0, 'operator' => Rule::OPERATOR_LTE]);

        $cart = new Cart('test');
        $context = static::createStub(SalesChannelContext::class);

        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testRuleWithGreaterThanEqualExactPriceMatch(): void
    {
        $rule = (new GoodsPriceRule())->assign(['amount' => 270.0, 'operator' => Rule::OPERATOR_GTE]);

        $cart = new Cart('test');
        $cart->add(
            (new LineItem('a', 'a'))
                ->setPrice(new CalculatedPrice(270, 270, new CalculatedTaxCollection(), new TaxRuleCollection()))
        );
        $context = static::createStub(SalesChannelContext::class);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testRuleWithGreaterThanEqualPriceMatch(): void
    {
        $rule = (new GoodsPriceRule())->assign(['amount' => 250.0, 'operator' => Rule::OPERATOR_GTE]);

        $cart = new Cart('test');
        $cart->add(
            (new LineItem('a', 'a'))
                ->setPrice(new CalculatedPrice(270, 270, new CalculatedTaxCollection(), new TaxRuleCollection()))
        );

        $context = static::createStub(SalesChannelContext::class);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testRuleWithGreaterThanEqualPriceNotMatch(): void
    {
        $rule = (new GoodsPriceRule())->assign(['amount' => 300.0, 'operator' => Rule::OPERATOR_GTE]);

        $cart = new Cart('test');
        $context = static::createStub(SalesChannelContext::class);

        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testRuleWithNotEqualPriceMatch(): void
    {
        $rule = (new GoodsPriceRule())->assign(['amount' => 200.0, 'operator' => Rule::OPERATOR_NEQ]);

        $cart = new Cart('test');
        $context = static::createStub(SalesChannelContext::class);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testRuleWithNotEqualPriceNotMatch(): void
    {
        $rule = (new GoodsPriceRule())->assign(['amount' => 270.0, 'operator' => Rule::OPERATOR_NEQ]);

        $cart = new Cart('test');
        $cart->add(
            (new LineItem('a', 'a'))
                ->setPrice(new CalculatedPrice(270, 270, new CalculatedTaxCollection(), new TaxRuleCollection()))
        );

        $context = static::createStub(SalesChannelContext::class);

        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testMatchWithWrongScopeShouldReturnFalse(): void
    {
        $goodsCountRule = new GoodsPriceRule();
        $wrongScope = static::createStub(RuleScope::class);

        static::assertFalse($goodsCountRule->match($wrongScope));
    }

    public function testMatchWithSimpleRule(): void
    {
        $goodsCountRule = new GoodsPriceRule(Rule::OPERATOR_EQ, 300.0);
        $goodsCountRule->setRules([new SimpleRule()]);

        static::assertTrue($goodsCountRule->match($this->createCartRuleScope()));
    }

    public function testMatchWithSimpleRuleExpectFalse(): void
    {
        $goodsCountRule = new GoodsPriceRule(Rule::OPERATOR_EQ, 300.0);
        $goodsCountRule->setRules([new SimpleRule(false)]);

        static::assertFalse($goodsCountRule->match($this->createCartRuleScope()));
    }

    public function testGetConstraints(): void
    {
        $goodsCountRule = new GoodsPriceRule();

        $result = $goodsCountRule->getConstraints();

        static::assertArrayHasKey('amount', $result);
        static::assertArrayHasKey('operator', $result);

        static::assertIsArray($result['amount']);
        static::assertIsArray($result['operator']);
    }

    #[DataProvider('getLineItemScopeTestData')]
    public function testIfMatchesAllCorrectWithLineItemScope(
        float $amount,
        string $operator,
        float $price,
        bool $expected
    ): void {
        $rule = (new GoodsPriceRule())->assign(['amount' => $amount, 'operator' => $operator]);

        $match = $rule->match(new LineItemScope(
            $this->createLineItemWithPrice($price),
            static::createStub(SalesChannelContext::class)
        ));

        static::assertSame($expected, $match);
    }

    /**
     * @return array<string, mixed[]>
     */
    public static function getLineItemScopeTestData(): array
    {
        return [
            'product / equal / match price' => [270.0, Rule::OPERATOR_EQ, 270.0, true],
            'product / equal / no match price' => [270.0, Rule::OPERATOR_EQ, 100.0, false],
            'product / lower than or equal / match price' => [270.0, Rule::OPERATOR_LTE, 250.0, true],
            'product / lower than or equal / no match price' => [270.0, Rule::OPERATOR_LTE, 280.0, false],
            'product / lower than or equal / match equal price' => [270.0, Rule::OPERATOR_LTE, 270.0, true],
            'product / greater than or equal / match price' => [270.0, Rule::OPERATOR_GTE, 280.0, true],
            'product / greater than or equal / no match price' => [270.0, Rule::OPERATOR_GTE, 260.0, false],
            'product / greater than or equal / match equal price' => [270.0, Rule::OPERATOR_GTE, 270.0, true],
            'product / not equal / match price' => [270.0, Rule::OPERATOR_NEQ, 250.0, true],
            'product / not equal / no match price' => [270.0, Rule::OPERATOR_NEQ, 270.0, false],
        ];
    }

    private function createCartRuleScope(): CartRuleScope
    {
        $cart = new Cart('test');
        $cart->add(
            (new LineItem('a', 'a'))
                ->setGood(true)
                ->setPrice(new CalculatedPrice(100, 100, new CalculatedTaxCollection(), new TaxRuleCollection()))
        );
        $cart->add(
            (new LineItem('b', 'a'))
                ->setGood(true)
                ->setPrice(new CalculatedPrice(100, 100, new CalculatedTaxCollection(), new TaxRuleCollection()))
        );

        $cart->add(
            (new LineItem('c', 'a'))
                ->setGood(true)
                ->setPrice(new CalculatedPrice(100, 100, new CalculatedTaxCollection(), new TaxRuleCollection()))
        );

        $context = static::createStub(SalesChannelContext::class);

        return new CartRuleScope($cart, $context);
    }

    private function createLineItemWithPrice(float $amount): LineItem
    {
        return CartRuleFixture::createLineItem()->setPrice(new CalculatedPrice($amount, $amount, new CalculatedTaxCollection(), new TaxRuleCollection()));
    }
}
