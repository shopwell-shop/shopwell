<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Loyalty\Entity\MembershipPlan;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<MembershipPlanEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class MembershipPlanCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'membership_plan_collection';
    }

    protected function getExpectedClass(): string
    {
        return MembershipPlanEntity::class;
    }
}
