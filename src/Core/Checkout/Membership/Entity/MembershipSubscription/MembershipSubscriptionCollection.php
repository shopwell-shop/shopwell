<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Membership\Entity\MembershipSubscription;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<MembershipSubscriptionEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class MembershipSubscriptionCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'membership_subscription_collection';
    }

    protected function getExpectedClass(): string
    {
        return MembershipSubscriptionEntity::class;
    }
}
