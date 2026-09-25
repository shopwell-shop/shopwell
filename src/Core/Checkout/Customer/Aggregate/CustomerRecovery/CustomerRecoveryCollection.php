<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Customer\Aggregate\CustomerRecovery;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<CustomerRecoveryEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class CustomerRecoveryCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'customer_recovery_collection';
    }

    protected function getExpectedClass(): string
    {
        return CustomerRecoveryEntity::class;
    }
}
