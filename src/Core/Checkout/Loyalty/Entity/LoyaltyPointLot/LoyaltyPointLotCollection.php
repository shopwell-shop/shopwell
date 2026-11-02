<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyPointLot;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<LoyaltyPointLotEntity>
 */
#[Package('checkout')]
class LoyaltyPointLotCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return LoyaltyPointLotEntity::class;
    }
}
