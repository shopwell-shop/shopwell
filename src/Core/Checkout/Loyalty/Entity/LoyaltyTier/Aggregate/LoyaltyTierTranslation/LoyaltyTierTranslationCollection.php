<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyTier\Aggregate\LoyaltyTierTranslation;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<LoyaltyTierTranslationEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class LoyaltyTierTranslationCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'loyalty_tier_translation_collection';
    }

    protected function getExpectedClass(): string
    {
        return LoyaltyTierTranslationEntity::class;
    }
}
