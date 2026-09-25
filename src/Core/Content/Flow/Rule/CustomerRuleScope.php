<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Flow\Rule;

use Shopwell\Core\Checkout\CheckoutRuleScope;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

/**
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
class CustomerRuleScope extends CheckoutRuleScope
{
    public function __construct(
        private readonly CustomerEntity $customer,
        SalesChannelContext $context,
    ) {
        parent::__construct($context);
    }

    public function getCustomer(): CustomerEntity
    {
        return $this->customer;
    }
}
