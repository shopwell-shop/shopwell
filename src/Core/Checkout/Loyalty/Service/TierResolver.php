<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Loyalty\Service;

use Psr\Clock\ClockInterface;
use Shopwell\Core\Checkout\Customer\CustomerCollection;
use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyAccount\LoyaltyAccountCollection;
use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyAccount\LoyaltyAccountEntity;
use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyTier\LoyaltyTierCollection;
use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyTier\LoyaltyTierEntity;
use Shopwell\Core\Checkout\Loyalty\Event\LoyaltyTierChangedEvent;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Which tier a customer is on, and keeping that answer on the account.
 *
 * The higher of the earned tier and a valid paid tier applies. Callers use currentTier() so expiry
 * takes effect immediately, even before the scheduled task updates the account projection.
 *
 * The column it writes is `loyalty_account.tier_id`: the *effective* tier, bought or earned. It is
 * separate from `paid_tier_id` and `membership_paid_until`, which hold the paid qualification.
 * A change is dispatched as {@see LoyaltyTierChangedEvent} once, when
 * the tier really differs, so a customer whose tier points grow inside one tier hears nothing.
 *
 * Moving the customer into the tier's customer group is a shop decision and off by default: a group
 * can carry tax and payment rules, and a points program must not silently change what a customer
 * pays. When it is on, the group follows the tier in the same step as the tier itself.
 *
 * @final
 */
#[Package('checkout')]
class TierResolver
{
    /**
     * @internal
     *
     * @param EntityRepository<LoyaltyTierCollection> $tierRepository
     * @param EntityRepository<LoyaltyAccountCollection> $loyaltyAccountRepository
     * @param EntityRepository<CustomerCollection> $customerRepository
     */
    public function __construct(
        private readonly EntityRepository $tierRepository,
        private readonly EntityRepository $loyaltyAccountRepository,
        private readonly EntityRepository $customerRepository,
        private readonly LoyaltySettings $settings,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ClockInterface $clock,
    ) {
    }

    public function resolve(string $customerId, int $tierPoints, Context $context): void
    {
        $loyalty = $this->findLoyalty($customerId, $context);

        if ($loyalty === null) {
            return;
        }

        $previousTierId = $loyalty->getTierId();
        $tier = $this->resolveTier($loyalty, $tierPoints, $context);
        $newTierId = $tier?->getId();

        if ($previousTierId === $newTierId) {
            return;
        }

        $this->loyaltyAccountRepository->update([[
            'id' => $loyalty->getId(),
            'tierId' => $newTierId,
        ]], $context);

        if ($this->settings->tierAutoGroupSync() && $tier?->getCustomerGroupId() !== null) {
            $this->customerRepository->update([[
                'id' => $customerId,
                'groupId' => $tier->getCustomerGroupId(),
            ]], $context);
        }

        $this->eventDispatcher->dispatch(new LoyaltyTierChangedEvent($customerId, $previousTierId, $newTierId, $context));
    }

    /**
     * The id of the tier a customer with the given tier points belongs to, or null when none of
     * the active tiers is reached yet. The tier is only assigned by {@see self::resolve()}, this
     * answers what resolves to - for example which tier a membership starts in.
     */
    public function findTierIdForTierPoints(int $tierPoints, Context $context): ?string
    {
        return $this->findMatchingTier($tierPoints, $context)?->getId();
    }

    /**
     * The tier the customer belongs to right now, or null when they reached none yet.
     *
     * What that tier grants is {@see LoyaltyBenefitResolver}'s question. This class only answers which
     * tier it is, so a benefit and its storage never have to be understood here.
     */
    public function currentTier(string $customerId, Context $context): ?LoyaltyTierEntity
    {
        $loyalty = $this->findLoyalty($customerId, $context);

        if ($loyalty === null) {
            return null;
        }

        return $this->resolveTier($loyalty, $loyalty->getTierPoints(), $context);
    }

    /**
     * The tier the customer belongs to right now.
     *
     * Buying a tier must neither downgrade a customer nor stop them earning a higher tier.
     * Expiry is checked here so the answer does not depend on the scheduled task having run.
     */
    private function resolveTier(LoyaltyAccountEntity $loyalty, int $tierPoints, Context $context): ?LoyaltyTierEntity
    {
        $paid = $this->findPaidTier($loyalty, $context);
        $earned = $this->findMatchingTier($tierPoints, $context);

        if ($earned !== null && ($paid === null || $earned->getLevel() > $paid->getLevel())) {
            return $earned;
        }

        return $paid;
    }

    private function findPaidTier(LoyaltyAccountEntity $loyalty, Context $context): ?LoyaltyTierEntity
    {
        $tierId = $loyalty->getPaidTierId();
        $validUntil = $loyalty->getMembershipPaidUntil();

        if ($tierId === null || $validUntil === null || $validUntil <= $this->clock->now()) {
            return null;
        }

        $tier = $this->tierRepository->search(new Criteria([$tierId]), $context)->getEntities()->first();

        return $tier instanceof LoyaltyTierEntity && $tier->getActive() ? $tier : null;
    }

    private function findMatchingTier(int $tierPoints, Context $context): ?LoyaltyTierEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addSorting(new FieldSorting('level', FieldSorting::ASCENDING));

        $matching = null;

        foreach ($this->tierRepository->search($criteria, $context)->getEntities() as $tier) {
            if (!($tier instanceof LoyaltyTierEntity) || ($tier->getMinTierPoints() ?? 0) > $tierPoints) {
                continue;
            }

            $matching = $tier;
        }

        return $matching;
    }

    private function findLoyalty(string $customerId, Context $context): ?LoyaltyAccountEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customerId', $customerId));
        $criteria->setLimit(1);

        $loyalty = $this->loyaltyAccountRepository->search($criteria, $context)->getEntities()->first();

        return $loyalty instanceof LoyaltyAccountEntity ? $loyalty : null;
    }
}
