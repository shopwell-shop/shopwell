<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout;

use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Rule\RuleScope;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
class CheckoutRuleScope extends RuleScope
{
    public function __construct(
        protected SalesChannelContext $context
    ) {
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->context;
    }

    public function getCustomer(): ?CustomerEntity
    {
        // a visitor who entered an address is matched as if they registered as a new guest right now
        return $this->context->getCustomer() ?? $this->context->getProspectiveCustomer()?->getRuleCustomer();
    }

    public function getContext(): Context
    {
        return $this->context->getContext();
    }
}
