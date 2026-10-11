<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Rule\Rule\Context;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Customer\Rule\NameRule;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(NameRule::class)]
class NameRuleTest extends TestCase
{
    public function testExactMatch(): void
    {
        $rule = (new NameRule())->assign(['name' => 'shopwell']);

        $cart = new Cart('test');

        $customer = new CustomerEntity();
        $customer->setName('shopwell');

        $context = static::createStub(SalesChannelContext::class);

        $context
            ->method('getCustomer')
            ->willReturn($customer);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testCaseInsensitive(): void
    {
        $rule = (new NameRule())->assign(['name' => 'shopwell']);

        $cart = new Cart('test');

        $customer = new CustomerEntity();
        $customer->setName('Shopwell');

        $context = static::createStub(SalesChannelContext::class);

        $context
            ->method('getCustomer')
            ->willReturn($customer);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testWithoutCustomer(): void
    {
        $rule = new NameRule();

        $cart = new Cart('test');

        $context = static::createStub(SalesChannelContext::class);

        $context
            ->method('getCustomer')
            ->willReturn(null);

        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }
}
