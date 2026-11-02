<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyTierBenefit;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<LoyaltyTierBenefitEntity>
 */
#[Package('checkout')]
class LoyaltyTierBenefitCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return LoyaltyTierBenefitEntity::class;
    }
}
