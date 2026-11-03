<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Membership\Service;

use Shopwell\Core\Checkout\Membership\Entity\MembershipPlan\MembershipPlanCollection;
use Shopwell\Core\Checkout\Membership\Entity\MembershipPlan\MembershipPlanEntity;
use Shopwell\Core\Checkout\Membership\MembershipException;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopwell\Core\Framework\Log\Package;

/**
 * The plans a shop offers.
 *
 * A plan is only the offer: name, price and duration are copied onto the purchase when it is written,
 * so editing or removing a plan never rewrites what a customer already bought.
 *
 * @final
 */
#[Package('checkout')]
class MembershipPlanService
{
    /**
     * @internal
     *
     * @param EntityRepository<MembershipPlanCollection> $planRepository
     */
    public function __construct(private readonly EntityRepository $planRepository)
    {
    }

    /**
     * The plans a customer of this currency can buy, cheapest first.
     *
     * @return list<MembershipPlanEntity>
     */
    public function purchasable(string $currencyId, Context $context): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('active', true),
            new EqualsFilter('currencyId', $currencyId)
        );
        // the tier and what it grants: the plan card prints the benefits, and loading them here keeps
        // the presenter free of a second query per plan
        $criteria->addAssociation('loyaltyTier.benefits');
        $criteria->addSorting(new FieldSorting('price', FieldSorting::ASCENDING));

        $plans = [];

        foreach ($this->planRepository->search($criteria, $context)->getEntities() as $plan) {
            if ($plan instanceof MembershipPlanEntity) {
                $plans[] = $plan;
            }
        }

        return $plans;
    }

    /**
     * @throws MembershipException when the plan does not exist, is not offered, or is priced in another currency
     */
    public function findPurchasable(string $planId, string $currencyId, Context $context): MembershipPlanEntity
    {
        $plan = $this->planRepository->search(new Criteria([$planId]), $context)->getEntities()->first();

        if (!$plan instanceof MembershipPlanEntity) {
            throw MembershipException::planNotFound($planId);
        }

        if (!$plan->isActive() || $plan->getCurrencyId() !== $currencyId) {
            throw MembershipException::planNotPurchasable($planId);
        }

        return $plan;
    }
}
