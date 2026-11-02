<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Loyalty\Service;

use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyRedemptionLevel\LoyaltyRedemptionLevelCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * What a number of points is worth in the currency of the order, as bands.
 *
 * A band is `[from, to, rate]` and bands never overlap: the first one starts at zero, every next one
 * where the previous ended, the last one is open. That makes the two operations the shop needs - "what
 * do I get for these points" and "how many points may I spend for this amount" - the same walk over
 * the same list, so they cannot drift apart.
 *
 * Immutable and free of the database: the ladder is loaded once, then answered any number of times
 * without another query, which is why the cart can ask both questions in one recalculation.
 *
 * Amounts are not rounded here. Rounding belongs to the price calculator that knows the currency
 * exponent, and rounding earlier would make `amountFor()` and the points it was derived from disagree.
 *
 * @final
 */
#[Package('checkout')]
class LoyaltyRedemptionRates
{
    /**
     * @param list<array{points: int, valuePerPoint: float}> $steps
     * @param list<array{0: int, 1: int|null, 2: float}> $bands from, to (null = open), value of one point
     */
    private function __construct(
        private readonly array $steps,
        private readonly array $bands,
    ) {
    }

    /**
     * Builds the bands out of the configured steps and the shop wide rate that applies below the first
     * step. A collection without steps answers with a single band at the shop wide rate, which is what
     * the shop did before the ladder existed.
     */
    public static function fromLevels(LoyaltyRedemptionLevelCollection $levels, float $baseRate): self
    {
        $steps = [];
        foreach ($levels as $level) {
            $steps[] = ['points' => $level->getPoints(), 'valuePerPoint' => max(0.0, $level->getValuePerPoint())];
        }

        usort($steps, static fn (array $a, array $b): int => $a['points'] <=> $b['points']);

        $bands = [];
        $from = 0;
        $rate = max(0.0, $baseRate);

        foreach ($steps as $step) {
            if ($step['points'] > $from) {
                $bands[] = [$from, $step['points'], $rate];
                $from = $step['points'];
            }

            // a step at or below the current start takes the band over from there, so a step at zero
            // replaces the shop wide rate and two steps at the same point cannot open a gap
            $rate = $step['valuePerPoint'];
        }

        $bands[] = [$from, null, $rate];

        return new self($steps, $bands);
    }

    /**
     * The amount these points pay for, band by band: the first thousand points at the base rate and
     * only what goes beyond a threshold at the better one.
     */
    public function amountFor(int $points): float
    {
        $amount = 0.0;

        foreach ($this->bands as [$from, $to, $rate]) {
            $upper = $to === null ? $points : min($points, $to);

            if ($upper <= $from) {
                break;
            }

            $amount += ($upper - $from) * $rate;
        }

        return $amount;
    }

    /**
     * The highest number of points whose amount does not exceed the given one - the inverse of
     * {@see self::amountFor()}, needed to keep a redemption inside the share of the order it may cover.
     *
     * It is the same walk turned around instead of a division, because the rate changes with the
     * number of points and a single division would only be right inside one band.
     */
    public function maxPointsFor(float $amount): int
    {
        $points = 0;
        $remaining = $amount;

        foreach ($this->bands as [$from, $to, $rate]) {
            $capacity = $to === null ? null : $to - $from;

            if ($rate <= 0.0) {
                // points that cost nothing would be endless, so the walk ends where the free band begins
                if ($capacity === null) {
                    return $points;
                }

                $points += $capacity;

                continue;
            }

            $fits = max(0, (int) floor($remaining / $rate));

            if ($capacity === null || $fits < $capacity) {
                return $points + $fits;
            }

            $points += $capacity;
            $remaining -= $capacity * $rate;
        }

        return $points;
    }

    /**
     * Whether redeeming can pay anything at all. A ladder of zeros and a shop wide rate of zero both
     * mean the shop does not sell points for money, which is a valid setting and not an error.
     */
    public function hasRate(): bool
    {
        foreach ($this->bands as [, , $rate]) {
            if ($rate > 0.0) {
                return true;
            }
        }

        return false;
    }

    /**
     * The configured steps, for anything that has to show the ladder rather than use it.
     *
     * @return list<array{points: int, valuePerPoint: float}>
     */
    public function levels(): array
    {
        return $this->steps;
    }
}
