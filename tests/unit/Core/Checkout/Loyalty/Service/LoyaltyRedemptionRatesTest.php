<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Loyalty\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyRedemptionLevel\LoyaltyRedemptionLevelCollection;
use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyRedemptionLevel\LoyaltyRedemptionLevelEntity;
use Shopwell\Core\Checkout\Loyalty\Service\LoyaltyRedemptionRates;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;

/**
 * The ladder is the only place that decides what points are worth, so these tests are arithmetic
 * rather than arrangement: the bands, the two directions of the same calculation and the cases where
 * the ladder is empty or says nothing is worth anything.
 *
 * @internal
 */
#[Package('checkout')]
#[CoversClass(LoyaltyRedemptionRates::class)]
class LoyaltyRedemptionRatesTest extends TestCase
{
    public function testWithoutStepsAPointIsWorthTheBaseRate(): void
    {
        $rates = LoyaltyRedemptionRates::fromLevels(new LoyaltyRedemptionLevelCollection(), 0.01);

        static::assertSame(10.0, $rates->amountFor(1000));
        static::assertTrue($rates->hasRate());
        static::assertSame([], $rates->levels());
    }

    public function testAStepOnlyChangesTheRateFromItsPointCountOn(): void
    {
        $rates = $this->rates(0.01, [1000 => 0.02]);

        // below the step the base rate still applies
        static::assertSame(5.0, $rates->amountFor(500));
        static::assertSame(10.0, $rates->amountFor(1000));
        // the thousandth point itself is already worth the better rate
        static::assertSame(10.0 + 500 * 0.02, $rates->amountFor(1500));
    }

    public function testTheLadderHasNoCliffAtAStep(): void
    {
        $rates = $this->rates(0.01, [1000 => 0.012, 5000 => 0.015]);

        $beforeStep = $rates->amountFor(4999);
        $atStep = $rates->amountFor(5000);

        static::assertGreaterThan($beforeStep, $atStep);
        // one more point never pays more than the best rate - the failure mode of a ladder that
        // switches the rate for the whole amount instead of the part above the step
        static::assertLessThanOrEqual(0.015, $atStep - $beforeStep);
    }

    public function testAStepAtZeroReplacesTheBaseRate(): void
    {
        $rates = $this->rates(0.01, [0 => 0.02]);

        static::assertSame(2.0, $rates->amountFor(100));
    }

    public function testStepsAreAnsweredInTheOrderTheyApply(): void
    {
        $rates = $this->rates(0.01, [5000 => 0.03, 1000 => 0.02]);

        static::assertSame([
            ['points' => 1000, 'valuePerPoint' => 0.02],
            ['points' => 5000, 'valuePerPoint' => 0.03],
        ], $rates->levels());

        static::assertSame(10.0 + 1000 * 0.02, $rates->amountFor(2000));
    }

    public function testMaxPointsForStaysInsideTheAmount(): void
    {
        $rates = $this->rates(0.01, [1000 => 0.02]);

        static::assertSame(0, $rates->maxPointsFor(0.0));
        static::assertSame(500, $rates->maxPointsFor(5.0));
        // 10.00 pays the first thousand points, the remaining 5.00 buys 250 points at 0.02
        static::assertSame(1250, $rates->maxPointsFor(15.0));
        static::assertSame(15.0, $rates->amountFor($rates->maxPointsFor(15.0)));
    }

    public function testMaxPointsForCrossesSeveralSteps(): void
    {
        $rates = $this->rates(0.01, [1000 => 0.02, 2000 => 0.05]);

        // 10.00 + 20.00 + 5.00 worth of points
        static::assertSame(2100, $rates->maxPointsFor(35.0));
    }

    public function testNothingIsRedeemableWithoutAPositiveRate(): void
    {
        $rates = LoyaltyRedemptionRates::fromLevels(new LoyaltyRedemptionLevelCollection(), 0.0);

        static::assertFalse($rates->hasRate());
        static::assertSame(0.0, $rates->amountFor(1000));
        static::assertSame(0, $rates->maxPointsFor(100.0));
    }

    public function testAFreeBandDoesNotGiveEndlessPoints(): void
    {
        $rates = $this->rates(0.01, [1000 => 0.0]);

        static::assertSame(10.0, $rates->amountFor(5000));
        static::assertSame(1000, $rates->maxPointsFor(999.0));
        static::assertTrue($rates->hasRate());
    }

    public function testUnusablePointsAreWorthNothing(): void
    {
        $rates = $this->rates(0.01, [1000 => 0.02]);

        static::assertSame(0.0, $rates->amountFor(0));
        static::assertSame(0.0, $rates->amountFor(-50));
        static::assertSame(0, $rates->maxPointsFor(-1.0));
    }

    /**
     * @param array<int, float> $steps
     */
    private function rates(float $baseRate, array $steps): LoyaltyRedemptionRates
    {
        $levels = new LoyaltyRedemptionLevelCollection();

        foreach ($steps as $points => $valuePerPoint) {
            $level = new LoyaltyRedemptionLevelEntity();
            $level->setId(Uuid::randomHex());
            $level->setPoints($points);
            $level->setValuePerPoint($valuePerPoint);
            $levels->add($level);
        }

        return LoyaltyRedemptionRates::fromLevels($levels, $baseRate);
    }
}
