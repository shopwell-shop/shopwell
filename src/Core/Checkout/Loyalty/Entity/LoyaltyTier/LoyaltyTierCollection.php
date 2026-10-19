<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyTier;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<LoyaltyTierEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class LoyaltyTierCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'loyalty_tier_collection';
    }

    protected function getExpectedClass(): string
    {
        return LoyaltyTierEntity::class;
    }
}
