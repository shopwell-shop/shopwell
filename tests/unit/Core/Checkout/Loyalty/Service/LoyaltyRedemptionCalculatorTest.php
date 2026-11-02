<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Loyalty\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyRedemptionLevel\LoyaltyRedemptionLevelCollection;
use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyRedemptionLevel\LoyaltyRedemptionLevelEntity;
use Shopwell\Core\Checkout\Loyalty\Service\LoyaltyRedemptionCalculator;
use Shopwell\Core\Checkout\Loyalty\Service\LoyaltySettings;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopwell\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;

/**
 * The calculator only joins two things: the stored steps and the shop wide rate. What matters here is
 * that it reads the steps at all, and that a missing configuration still leaves a usable ladder
 * instead of a rerun of the "one point is worth nothing" defect.
 *
 * @internal
 */
#[Package('checkout')]
#[CoversClass(LoyaltyRedemptionCalculator::class)]
class LoyaltyRedemptionCalculatorTest extends TestCase
{
    public function testItAnswersWithTheStoredLadder(): void
    {
        $rates = $this->calculator(0.01, [1000 => 0.02])->rates(null, Context::createDefaultContext());

        static::assertSame(10.0 + 500 * 0.02, $rates->amountFor(1500));
    }

    public function testItFallsBackToTheConfiguredRateWhenNoStepIsStored(): void
    {
        $rates = $this->calculator(0.01, [])->rates(null, Context::createDefaultContext());

        static::assertSame([], $rates->levels());
        static::assertSame(10.0, $rates->amountFor(1000));
    }

    public function testItFallsBackToTheDefaultRateWhenTheSettingWasNeverSaved(): void
    {
        $rates = $this->calculator(null, [])->rates(null, Context::createDefaultContext());

        static::assertTrue($rates->hasRate());
        static::assertSame(
            LoyaltySettings::DEFAULT_POINT_VALUE_IN_CURRENCY * 1000,
            $rates->amountFor(1000)
        );
    }

    /**
     * @param array<int, float> $steps
     */
    private function calculator(?float $baseRate, array $steps): LoyaltyRedemptionCalculator
    {
        $levels = new LoyaltyRedemptionLevelCollection();

        foreach ($steps as $points => $valuePerPoint) {
            $level = new LoyaltyRedemptionLevelEntity();
            $level->setId(Uuid::randomHex());
            $level->setPoints($points);
            $level->setValuePerPoint($valuePerPoint);
            $levels->add($level);
        }

        $config = $baseRate === null
            ? []
            : [LoyaltySettings::CONFIG_KEY_POINT_VALUE_IN_CURRENCY => $baseRate];

        return new LoyaltyRedemptionCalculator(
            StaticEntityRepository::of(LoyaltyRedemptionLevelCollection::class, [$levels]),
            new LoyaltySettings(new StaticSystemConfigService($config)),
        );
    }
}
