<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Loyalty\Entity\MembershipPlan\Aggregate\MembershipPlanTranslation;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<MembershipPlanTranslationEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class MembershipPlanTranslationCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'membership_plan_translation_collection';
    }

    protected function getExpectedClass(): string
    {
        return MembershipPlanTranslationEntity::class;
    }
}
