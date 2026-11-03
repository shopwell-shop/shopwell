<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Membership\Entity\MembershipPurchase;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<MembershipPurchaseEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class MembershipPurchaseCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'membership_purchase_collection';
    }

    protected function getExpectedClass(): string
    {
        return MembershipPurchaseEntity::class;
    }
}
