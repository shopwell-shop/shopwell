<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyRedemptionLevel;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<LoyaltyRedemptionLevelEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class LoyaltyRedemptionLevelCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'loyalty_redemption_level_collection';
    }

    protected function getExpectedClass(): string
    {
        return LoyaltyRedemptionLevelEntity::class;
    }
}
