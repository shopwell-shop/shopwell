<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Loyalty\Entity\MembershipOrder;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<MembershipOrderEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class MembershipOrderCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'membership_order_collection';
    }

    protected function getExpectedClass(): string
    {
        return MembershipOrderEntity::class;
    }
}
