<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Loyalty\Service;

use Shopwell\Core\Framework\Log\Package;

/**
 * Where a customer stands on the tier ladder.
 *
 * The ladder is read from the payload the loyalty route already returns, so every account page prints
 * the same step. Callers must not recompute "which tier" or "how far to the next one" themselves: two
 * pages doing that arithmetic would drift apart the moment the formula changes, and a progress bar that
 * disagrees with the sentence next to it is worse than no progress bar.
 *
 * Pure projection on purpose - no repository, no context, no storage. That keeps it usable from a page
 * loader without breaking the rule that a page loader reads through a store-api route only.
 *
 * @final
 */
#[Package('checkout')]
class LoyaltyTierProgress
{
    /**
     * The tier the customer is in and the one they are climbing towards.
     *
     * The current tier is matched by id rather than by tier points: a membership can grant a tier the
     * tier points alone would not reach, and the progress bar then has to start at that tier.
     *
     * @param list<array<string, mixed>> $tiers
     *
     * @return array{0: array<string, mixed>|null, 1: array<string, mixed>|null}
     */
    public function resolve(array $tiers, mixed $tierId): array
    {
        $currentTier = null;

        foreach ($tiers as $tier) {
            if (($tier['id'] ?? null) === $tierId) {
                $currentTier = $tier;
                break;
            }
        }

        if ($currentTier === null) {
            return [null, $tiers[0] ?? null];
        }

        foreach ($tiers as $tier) {
            if (($tier['minTierPoints'] ?? 0) > ($currentTier['minTierPoints'] ?? 0)) {
                return [$currentTier, $tier];
            }
        }

        return [$currentTier, null];
    }

    /**
     * How much of the way from the current tier to the next one the customer has walked, in percent.
     *
     * A customer without a current tier starts at 0, one that already reached the top tier has nothing
     * left to climb and therefore no progress - both answer 0 instead of a division by zero.
     *
     * @param array<string, mixed>|null $currentTier
     * @param array<string, mixed>|null $nextTier
     */
    public function percentage(int $tierPoints, ?array $currentTier, ?array $nextTier): int
    {
        if ($nextTier === null) {
            return 0;
        }

        $from = (int) ($currentTier['minTierPoints'] ?? 0);
        $range = (int) ($nextTier['minTierPoints'] ?? 0) - $from;

        if ($range <= 0) {
            return 0;
        }

        $reached = (int) floor((($tierPoints - $from) / $range) * 100);

        return max(0, min(100, $reached));
    }

    /**
     * How many tier points are still missing for the next tier - 0 when there is nothing left to reach.
     *
     * @param array<string, mixed>|null $nextTier
     */
    public function remainingPoints(int $tierPoints, ?array $nextTier): int
    {
        if ($nextTier === null) {
            return 0;
        }

        return max(0, (int) ($nextTier['minTierPoints'] ?? 0) - $tierPoints);
    }
}
