<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyAccount;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<LoyaltyAccountEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class LoyaltyAccountCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'loyalty_account_collection';
    }

    protected function getExpectedClass(): string
    {
        return LoyaltyAccountEntity::class;
    }
}
