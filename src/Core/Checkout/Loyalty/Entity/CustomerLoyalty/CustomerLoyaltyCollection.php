<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Loyalty\Entity\CustomerLoyalty;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<CustomerLoyaltyEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class CustomerLoyaltyCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'customer_loyalty_collection';
    }

    protected function getExpectedClass(): string
    {
        return CustomerLoyaltyEntity::class;
    }
}
