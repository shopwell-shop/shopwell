<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyPointTransaction;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<LoyaltyPointTransactionEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class LoyaltyPointTransactionCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'loyalty_point_transaction_collection';
    }

    protected function getExpectedClass(): string
    {
        return LoyaltyPointTransactionEntity::class;
    }
}
