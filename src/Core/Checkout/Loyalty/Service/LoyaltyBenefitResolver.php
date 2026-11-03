<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Loyalty\Service;

use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyTier\LoyaltyTierEntity;
use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyTierBenefit\LoyaltyTierBenefitCollection;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopwell\Core\Framework\Log\Package;

/**
 * Answers what a customer's tier grants.
 *
 * Callers ask this and never the tier table: the cart asks for a discount, the point booking asks for
 * a multiplier, the account page asks for everything to print. Keeping the question here means the
 * number of places that know how a benefit is stored stays one - which is what makes it possible to
 * change the storage at all.
 *
 * It reads the tier through {@see TierResolver}, so a paid membership outranking the tier points is
 * decided in one place and not repeated for every benefit.
 *
 * A benefit is a row of `loyalty_tier_benefit` and nothing else. The tier used to carry three fixed
 * columns for the multiplier, the discount and free shipping; those are gone, and with them the
 * fallback that read them when a row was missing. There is one storage, so there is one answer.
 *
 * @final
 */
#[Package('checkout')]
class LoyaltyBenefitResolver
{
    /**
     * @internal
     *
     * @param EntityRepository<LoyaltyTierBenefitCollection> $benefitRepository
     */
    public function __construct(
        private readonly EntityRepository $benefitRepository,
        private readonly TierResolver $tiers,
    ) {
    }

    /**
     * The benefits the customer holds right now - none when they reached no tier yet.
     */
    public function forCustomer(string $customerId, Context $context): LoyaltyTierBenefits
    {
        return $this->forTier($this->tiers->currentTier($customerId, $context), $context);
    }

    public function forTier(?LoyaltyTierEntity $tier, Context $context): LoyaltyTierBenefits
    {
        if ($tier === null) {
            return LoyaltyTierBenefits::none();
        }

        // a caller that loaded the tier with its association pays one query for all benefits, one that
        // did not gets them here - both end up with the same answer
        $loaded = $tier->getBenefits();

        return $loaded === null ? $this->read($tier->getId(), $context) : LoyaltyTierBenefits::of($loaded);
    }

    /**
     * The factor the customer's points are multiplied by. `1.0` means "no bonus", which is what a
     * customer without a tier earns.
     */
    public function earnMultiplier(string $customerId, Context $context): float
    {
        return $this->forCustomer($customerId, $context)->earnMultiplier();
    }

    private function read(string $tierId, Context $context): LoyaltyTierBenefits
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('loyaltyTierId', $tierId));
        $criteria->addSorting(new FieldSorting('position', FieldSorting::ASCENDING));

        $benefits = $this->benefitRepository->search($criteria, $context)->getEntities();

        if (!$benefits instanceof LoyaltyTierBenefitCollection) {
            return LoyaltyTierBenefits::none();
        }

        return LoyaltyTierBenefits::of($benefits);
    }
}
