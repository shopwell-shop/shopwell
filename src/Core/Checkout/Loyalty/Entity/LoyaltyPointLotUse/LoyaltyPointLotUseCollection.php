<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyPointLotUse;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<LoyaltyPointLotUseEntity>
 */
#[Package('checkout')]
class LoyaltyPointLotUseCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return LoyaltyPointLotUseEntity::class;
    }
}
