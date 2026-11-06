<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Checkout\Loyalty\Service;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyRedemptionLevel\LoyaltyRedemptionLevelCollection;
use Shopwell\Core\Checkout\Loyalty\Service\LoyaltyRedemptionCalculator;
use Shopwell\Core\Checkout\Loyalty\Service\LoyaltySettings;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;

/**
 * The ladder against the real database: the rows an administrator stores have to come back as the same
 * bands, in the order of their point count and not in the order they were written.
 *
 * @internal
 */
#[Package('checkout')]
class LoyaltyRedemptionCalculatorTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use IntegrationTestBehaviour;

    private Context $context;

    /**
     * @var EntityRepository<LoyaltyRedemptionLevelCollection>
     */
    private EntityRepository $levels;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
        $this->levels = static::getContainer()->get('loyalty_redemption_level.repository');
    }

    public function testStoredStepsBecomeBands(): void
    {
        // written in the wrong order on purpose: the ladder is read by point count, not by insertion
        $this->createLevel(5000, 0.015);
        $this->createLevel(1000, 0.012);

        $rates = $this->calculator()->rates(null, $this->context);

        static::assertSame([
            ['points' => 1000, 'valuePerPoint' => 0.012],
            ['points' => 5000, 'valuePerPoint' => 0.015],
        ], $rates->levels());

        // the base rate below the first step, the better rate only above it
        static::assertSame(1000 * 0.01 + 2000 * 0.012, $rates->amountFor(3000));
    }

    public function testAnEmptyLadderStillAnswersWithTheConfiguredRate(): void
    {
        $baseRate = static::getContainer()->get(LoyaltySettings::class)->pointValueInCurrency();

        $rates = $this->calculator()->rates(null, $this->context);

        static::assertSame([], $rates->levels());
        static::assertSame($baseRate * 500, $rates->amountFor(500));
    }

    private function createLevel(int $points, float $valuePerPoint): void
    {
        $this->levels->create([[
            'id' => Uuid::randomHex(),
            'points' => $points,
            'valuePerPoint' => $valuePerPoint,
        ]], $this->context);
    }

    private function calculator(): LoyaltyRedemptionCalculator
    {
        return new LoyaltyRedemptionCalculator(
            $this->levels,
            static::getContainer()->get(LoyaltySettings::class),
        );
    }
}
