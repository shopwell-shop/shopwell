<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Loyalty\Service;

use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyRedemptionLevel\LoyaltyRedemptionLevelCollection;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopwell\Core\Framework\Log\Package;

/**
 * Loads the redemption ladder and hands it out as {@see LoyaltyRedemptionRates}.
 *
 * Loading and deciding are two jobs: this class knows where the steps live, the rates object knows what
 * they mean. Everything that asks "what are these points worth" goes through here, so a redemption in
 * the cart, an order that books the points back and any later display cannot end up with three
 * different answers.
 *
 * The ladder is read on every call instead of being cached in the service. The table holds a handful of
 * rows and one indexed read is cheap; a cache on a shared service would survive an administration edit
 * inside a running worker and serve the old rates until the process restarts, which costs far more to
 * diagnose than the query saves.
 *
 * @final
 */
#[Package('checkout')]
class LoyaltyRedemptionCalculator
{
    /**
     * @internal
     *
     * @param EntityRepository<LoyaltyRedemptionLevelCollection> $levelRepository
     */
    public function __construct(
        private readonly EntityRepository $levelRepository,
        private readonly LoyaltySettings $settings,
    ) {
    }

    /**
     * @param string|null $salesChannelId only decides the shop wide rate below the first step; the steps
     *                                    themselves belong to the programme, not to a channel
     */
    public function rates(?string $salesChannelId, Context $context): LoyaltyRedemptionRates
    {
        $criteria = new Criteria();
        $criteria->addSorting(new FieldSorting('points', FieldSorting::ASCENDING));

        return LoyaltyRedemptionRates::fromLevels(
            $this->levelRepository->search($criteria, $context)->getEntities(),
            $this->settings->pointValueInCurrency($salesChannelId),
        );
    }
}
