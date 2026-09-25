<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Rule\Rule\Context;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopwell\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopwell\Core\Checkout\Customer\Rule\CustomerGroupRule;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Test\Generator;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(CustomerGroupRule::class)]
class CustomerGroupRuleTest extends TestCase
{
    public function testMatch(): void
    {
        $rule = (new CustomerGroupRule())->assign(['customerGroupIds' => ['SWAG-CUSTOMER-GROUP-ID-1']]);

        $cart = new Cart('test');

        $group = new CustomerGroupEntity();
        $group->setId('SWAG-CUSTOMER-GROUP-ID-1');

        $context = Generator::generateSalesChannelContext(currentCustomerGroup: $group);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testMultipleGroups(): void
    {
        $rule = (new CustomerGroupRule())->assign(['customerGroupIds' => ['SWAG-CUSTOMER-GROUP-ID-2', 'SWAG-CUSTOMER-GROUP-ID-3', 'SWAG-CUSTOMER-GROUP-ID-1']]);

        $cart = new Cart('test');

        $group = new CustomerGroupEntity();
        $group->setId('SWAG-CUSTOMER-GROUP-ID-3');

        $context = Generator::generateSalesChannelContext(currentCustomerGroup: $group);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testNotMatch(): void
    {
        $rule = (new CustomerGroupRule())->assign(['customerGroupIds' => ['SWAG-CUSTOMER-GROUP-ID-2', 'SWAG-CUSTOMER-GROUP-ID-3', 'SWAG-CUSTOMER-GROUP-ID-1']]);

        $cart = new Cart('test');

        $group = new CustomerGroupEntity();
        $group->setId('SWAG-CUSTOMER-GROUP-ID-5');

        $context = Generator::generateSalesChannelContext(currentCustomerGroup: $group);

        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }
}
