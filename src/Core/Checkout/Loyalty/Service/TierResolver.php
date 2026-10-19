<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Loyalty\Service;

use Psr\Clock\ClockInterface;
use Shopwell\Core\Checkout\Customer\CustomerCollection;
use Shopwell\Core\Checkout\Loyalty\Entity\CustomerLoyalty\CustomerLoyaltyCollection;
use Shopwell\Core\Checkout\Loyalty\Entity\CustomerLoyalty\CustomerLoyaltyEntity;
use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyTier\LoyaltyTierCollection;
use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyTier\LoyaltyTierEntity;
use Shopwell\Core\Checkout\Loyalty\Event\LoyaltyTierChangedEvent;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SystemConfig\SystemConfigService;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @final
 */
#[Package('checkout')]
class TierResolver
{
    final public const CONFIG_KEY_TIER_AUTO_GROUP_SYNC = 'core.loyalty.tierAutoGroupSync';

    /**
     * @internal
     *
     * @param EntityRepository<LoyaltyTierCollection> $tierRepository
     * @param EntityRepository<CustomerLoyaltyCollection> $customerLoyaltyRepository
     * @param EntityRepository<CustomerCollection> $customerRepository
     */
    public function __construct(
        private readonly EntityRepository $tierRepository,
        private readonly EntityRepository $customerLoyaltyRepository,
        private readonly EntityRepository $customerRepository,
        private readonly SystemConfigService $systemConfigService,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ClockInterface $clock,
    ) {
    }

    public function resolve(string $customerId, int $growthValue, Context $context): void
    {
        $loyalty = $this->findLoyalty($customerId, $context);

        if ($loyalty === null) {
            return;
        }

        $previousTierId = $loyalty->getMembershipTierId();
        $tier = $this->resolveTier($loyalty, $growthValue, $context);
        $newTierId = $tier?->getId();

        if ($previousTierId === $newTierId) {
            return;
        }

        $this->customerLoyaltyRepository->update([[
            'id' => $loyalty->getId(),
            'membershipTierId' => $newTierId,
        ]], $context);

        if ($this->systemConfigService->getBool(self::CONFIG_KEY_TIER_AUTO_GROUP_SYNC) && $tier?->getCustomerGroupId() !== null) {
            $this->customerRepository->update([[
                'id' => $customerId,
                'groupId' => $tier->getCustomerGroupId(),
            ]], $context);
        }

        $this->eventDispatcher->dispatch(new LoyaltyTierChangedEvent($customerId, $previousTierId, $newTierId, $context));
    }

    /**
     * The id of the tier a customer with the given growth value belongs to, or null when none of
     * the active tiers is reached yet. The tier is only assigned by {@see self::resolve()}, this
     * answers what resolves to - for example which tier a membership starts in.
     */
    public function findTierIdForGrowthValue(int $growthValue, Context $context): ?string
    {
        return $this->findMatchingTier($growthValue, $context)?->getId();
    }

    /**
     * Returns the points multiplier of the tier the customer currently belongs to.
     */
    public function getPointsMultiplier(string $customerId, Context $context): float
    {
        $loyalty = $this->findLoyalty($customerId, $context);

        if ($loyalty === null) {
            return 1.0;
        }

        $tier = $this->resolveTier($loyalty, $loyalty->getGrowthValue(), $context);

        return $tier?->getPointsMultiplier() ?? 1.0;
    }

    /**
     * The tier the customer belongs to right now.
     *
     * A paid membership outranks the growth value for as long as it runs - buying a tier must not be
     * undone by the next order that only reaches a lower one. Once it ended, the growth value decides
     * again, which is why the end of the membership is checked here and not in a clean up task.
     */
    private function resolveTier(CustomerLoyaltyEntity $loyalty, int $growthValue, Context $context): ?LoyaltyTierEntity
    {
        return $this->findPaidTier($loyalty, $context) ?? $this->findMatchingTier($growthValue, $context);
    }

    private function findPaidTier(CustomerLoyaltyEntity $loyalty, Context $context): ?LoyaltyTierEntity
    {
        $tierId = $loyalty->getMembershipTierId();
        $validUntil = $loyalty->getMembershipValidUntil();

        if ($tierId === null || $validUntil === null || $validUntil <= $this->clock->now()) {
            return null;
        }

        $tier = $this->tierRepository->search(new Criteria([$tierId]), $context)->getEntities()->first();

        return $tier instanceof LoyaltyTierEntity && $tier->getActive() ? $tier : null;
    }

    private function findMatchingTier(int $growthValue, Context $context): ?LoyaltyTierEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addSorting(new FieldSorting('level', FieldSorting::ASCENDING));

        $matching = null;

        foreach ($this->tierRepository->search($criteria, $context)->getEntities() as $tier) {
            if (!($tier instanceof LoyaltyTierEntity) || ($tier->getMinGrowthValue() ?? 0) > $growthValue) {
                continue;
            }

            $matching = $tier;
        }

        return $matching;
    }

    private function findLoyalty(string $customerId, Context $context): ?CustomerLoyaltyEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customerId', $customerId));
        $criteria->setLimit(1);

        $loyalty = $this->customerLoyaltyRepository->search($criteria, $context)->getEntities()->first();

        return $loyalty instanceof CustomerLoyaltyEntity ? $loyalty : null;
    }
}
