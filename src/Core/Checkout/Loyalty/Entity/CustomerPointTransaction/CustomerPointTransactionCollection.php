<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Loyalty\Entity\CustomerPointTransaction;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<CustomerPointTransactionEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class CustomerPointTransactionCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'customer_point_transaction_collection';
    }

    protected function getExpectedClass(): string
    {
        return CustomerPointTransactionEntity::class;
    }
}
