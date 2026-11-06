<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Loyalty\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Loyalty\Service\LoyaltyTierProgress;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(LoyaltyTierProgress::class)]
class LoyaltyTierProgressTest extends TestCase
{
    public function testResolveReturnsTheTierTheCustomerIsInAndTheOneAboveIt(): void
    {
        $progress = new LoyaltyTierProgress();

        [$currentTier, $nextTier] = $progress->resolve($this->ladder(), 'tier-gold');

        static::assertSame('Gold', $currentTier['name'] ?? null);
        static::assertSame('Platinum', $nextTier['name'] ?? null);
    }

    public function testResolveLooksTheTierUpByIdSoAMembershipCanGrantOneAboveTheCollectedPoints(): void
    {
        $progress = new LoyaltyTierProgress();

        // the customer holds 250 tier points - measured by points alone the ladder would put them on Gold
        [$currentTier, $nextTier] = $progress->resolve($this->ladder(), 'tier-platinum');

        static::assertSame('Platinum', $currentTier['name'] ?? null);
        static::assertNull($nextTier);
    }

    public function testResolveWithoutAMatchingTierHandsBackTheFirstRung(): void
    {
        $progress = new LoyaltyTierProgress();

        [$currentTier, $nextTier] = $progress->resolve($this->ladder(), null);

        static::assertNull($currentTier);
        // the first rung is what the customer climbs towards, so the bar has a target
        static::assertSame('Silver', $nextTier['name'] ?? null);
    }

    public function testResolveOnAShopWithoutTiersAnswersNothingInsteadOfAnError(): void
    {
        $progress = new LoyaltyTierProgress();

        [$currentTier, $nextTier] = $progress->resolve([], 'tier-gold');

        static::assertNull($currentTier);
        static::assertNull($nextTier);
    }

    public function testResolveFallsBackToTheFirstRungWhenTheIdIsUnknown(): void
    {
        $progress = new LoyaltyTierProgress();

        [$currentTier, $nextTier] = $progress->resolve($this->ladder(), 'tier-diamond');

        static::assertNull($currentTier);
        static::assertSame('Silver', $nextTier['name'] ?? null);
    }

    public function testPercentageIsTheShareOfTheStepBetweenTheTwoTiers(): void
    {
        $progress = new LoyaltyTierProgress();

        // on the demo shop: 15000 tier points, Gold starts at 5000, Platinum at 20000
        static::assertSame(66, $progress->percentage(
            15000,
            $this->tier('tier-gold', 'Gold', 5000),
            $this->tier('tier-platinum', 'Platinum', 20000)
        ));
    }

    public function testPercentageAtTheStartOfTheStepIsZero(): void
    {
        $progress = new LoyaltyTierProgress();

        static::assertSame(0, $progress->percentage(
            5000,
            $this->tier('tier-gold', 'Gold', 5000),
            $this->tier('tier-platinum', 'Platinum', 20000)
        ));
    }

    public function testPercentageWithoutANextTierIsZero(): void
    {
        $progress = new LoyaltyTierProgress();

        // the top tier has nothing left to climb: no progress is the only honest answer, and it keeps
        // the division by the width of a step that does not exist out of the formula
        static::assertSame(0, $progress->percentage(
            25000,
            $this->tier('tier-platinum', 'Platinum', 20000),
            null
        ));
    }

    public function testPercentageWithoutACurrentTierCountsFromTheBottomOfTheShop(): void
    {
        $progress = new LoyaltyTierProgress();

        // a customer without a tier stands at 0, so the way to the first rung is the whole step
        static::assertSame(0, $progress->percentage(0, null, $this->tier('tier-gold', 'Gold', 500)));
        static::assertSame(50, $progress->percentage(250, null, $this->tier('tier-gold', 'Gold', 500)));
    }

    public function testPercentageNeverLeavesTheBar(): void
    {
        $progress = new LoyaltyTierProgress();

        // more tier points than the next tier needs (a membership can grant that) is still a full bar
        static::assertSame(100, $progress->percentage(
            30000,
            $this->tier('tier-gold', 'Gold', 5000),
            $this->tier('tier-platinum', 'Platinum', 20000)
        ));

        // fewer tier points than the current tier starts at stays an empty bar instead of a negative one
        static::assertSame(0, $progress->percentage(
            100,
            $this->tier('tier-gold', 'Gold', 5000),
            $this->tier('tier-platinum', 'Platinum', 20000)
        ));
    }

    public function testPercentageWithTwoTiersOnTheSameStepIsZero(): void
    {
        $progress = new LoyaltyTierProgress();

        // two tiers configured with the same threshold: the step has no width, so there is nothing to fill
        static::assertSame(0, $progress->percentage(
            1500,
            $this->tier('tier-gold', 'Gold', 1000),
            $this->tier('tier-platinum', 'Platinum', 1000)
        ));
    }

    public function testRemainingPointsIsTheGapToTheNextTier(): void
    {
        $progress = new LoyaltyTierProgress();

        static::assertSame(5000, $progress->remainingPoints(15000, $this->tier('tier-platinum', 'Platinum', 20000)));
    }

    public function testRemainingPointsWithoutANextTierIsZero(): void
    {
        $progress = new LoyaltyTierProgress();

        static::assertSame(0, $progress->remainingPoints(25000, null));
    }

    #[DataProvider('tierPointsAboveTheNextTier')]
    public function testRemainingPointsNeverGoesNegative(int $tierPoints): void
    {
        $progress = new LoyaltyTierProgress();

        static::assertSame(0, $progress->remainingPoints($tierPoints, $this->tier('tier-platinum', 'Platinum', 20000)));
    }

    /**
     * @return iterable<string, array{0: int}>
     */
    public static function tierPointsAboveTheNextTier(): iterable
    {
        yield 'exactly on the next tier' => [20000];
        yield 'already past the next tier' => [25000];
    }

    /**
     * The ladder the loyalty route sends: silver at 0, gold at 200, platinum at 1000.
     *
     * @return list<array<string, mixed>>
     */
    private function ladder(): array
    {
        return [
            $this->tier('tier-silver', 'Silver', 0),
            $this->tier('tier-gold', 'Gold', 200),
            $this->tier('tier-platinum', 'Platinum', 1000),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function tier(string $id, string $name, int $minTierPoints): array
    {
        return [
            'id' => $id,
            'name' => $name,
            'minTierPoints' => $minTierPoints,
            'benefits' => [],
        ];
    }
}
