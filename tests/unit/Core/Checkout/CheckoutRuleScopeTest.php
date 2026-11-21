<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\CheckoutRuleScope;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Customer\ProspectiveAddress;
use Shopwell\Core\Checkout\Customer\ProspectiveCustomer;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Test\Generator;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CheckoutRuleScope::class)]
class CheckoutRuleScopeTest extends TestCase
{
    public function testConstruct(): void
    {
        $scope = new CheckoutRuleScope($context = Generator::generateSalesChannelContext());

        static::assertSame($context, $scope->getSalesChannelContext());
        static::assertSame($context->getContext(), $scope->getContext());
    }

    public function testTheCustomerOfTheContextWinsOverTheProspectiveCustomer(): void
    {
        $context = Generator::generateSalesChannelContext();
        $context->setProspectiveCustomer(new ProspectiveCustomer(new ProspectiveAddress('country-id'), null, new CustomerEntity()));

        static::assertSame($context->getCustomer(), (new CheckoutRuleScope($context))->getCustomer());
    }

    public function testWithoutCustomerTheRuleCustomerOfTheProspectiveCustomerIsMatched(): void
    {
        $context = Generator::generateSalesChannelContext();
        $context->assign(['customer' => null]);
        $ruleCustomer = new CustomerEntity();
        $context->setProspectiveCustomer(new ProspectiveCustomer(new ProspectiveAddress('country-id'), null, $ruleCustomer));

        static::assertSame($ruleCustomer, (new CheckoutRuleScope($context))->getCustomer());
        static::assertNull($context->getCustomer(), 'The rule customer must never become the customer of the context');
    }

    public function testWithoutCustomerAndProspectiveCustomerNoCustomerIsMatched(): void
    {
        $context = Generator::generateSalesChannelContext();
        $context->assign(['customer' => null]);

        static::assertNull((new CheckoutRuleScope($context))->getCustomer());
    }
}
