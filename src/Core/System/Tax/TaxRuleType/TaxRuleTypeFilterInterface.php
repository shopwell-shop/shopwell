<?php declare(strict_types=1);

namespace Shopwell\Core\System\Tax\TaxRuleType;

use Shopwell\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Tax\Aggregate\TaxRule\TaxRuleEntity;

#[Package('checkout')]
interface TaxRuleTypeFilterInterface
{
    public function match(TaxRuleEntity $taxRuleEntity, ?CustomerEntity $customer, ShippingLocation $shippingLocation): bool;
}
