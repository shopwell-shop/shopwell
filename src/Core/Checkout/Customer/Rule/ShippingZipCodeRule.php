<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Customer\Rule;

use Shopwell\Core\Checkout\CheckoutRuleScope;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Rule\Container\ZipCodeRule;
use Shopwell\Core\Framework\Rule\RuleComparison;
use Shopwell\Core\Framework\Rule\RuleScope;

#[Package('fundamentals@after-sales')]
class ShippingZipCodeRule extends ZipCodeRule
{
    final public const RULE_NAME = 'customerShippingZipCode';

    public function match(RuleScope $scope): bool
    {
        if (!$scope instanceof CheckoutRuleScope) {
            return false;
        }

        $address = $scope->getSalesChannelContext()->getShippingLocation()->getAddress()
            ?? $scope->getCustomer()?->getActiveShippingAddress();

        if (!$address) {
            return RuleComparison::isNegativeOperator($this->operator);
        }

        return $this->matchZipCode($address);
    }
}
