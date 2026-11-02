<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Loyalty\Service;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyPointLot\LoyaltyPointLotCollection;
use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyPointLotUse\LoyaltyPointLotUseCollection;
use Shopwell\Core\Checkout\Loyalty\Service\LoyaltyPointLots;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Clock\MockClock;

/**
 * The decision which batch gives how much is a pure function on purpose: it is the part of the
 * expiry, the redemption and the return that customers notice when it is wrong, and it can be read
 * here without a database in the way.
 *
 * @internal
 */
#[Package('checkout')]
#[CoversClass(LoyaltyPointLots::class)]
class LoyaltyPointLotsTest extends TestCase
{
    private const NOW = '2026-11-02 12:00:00';

    private LoyaltyPointLots $lots;

    protected function setUp(): void
    {
        $this->lots = new LoyaltyPointLots(
            static::createStub(Connection::class),
            StaticEntityRepository::of(LoyaltyPointLotCollection::class),
            StaticEntityRepository::of(LoyaltyPointLotUseCollection::class),
            new MockClock(self::NOW),
        );
    }

    public function testOneBatchThatIsEnoughCoversTheWholeRequest(): void
    {
        $allocation = $this->lots->allocate([['id' => 'a', 'remaining' => 100]], 40);

        static::assertSame([['lotId' => 'a', 'remaining' => 100, 'points' => 40]], $allocation);
    }

    public function testTheRequestIsSpreadOverTheBatchesInTheirOrder(): void
    {
        $allocation = $this->lots->allocate([
            ['id' => 'a', 'remaining' => 30],
            ['id' => 'b', 'remaining' => 50],
            ['id' => 'c', 'remaining' => 90],
        ], 100);

        static::assertSame([
            ['lotId' => 'a', 'remaining' => 30, 'points' => 30],
            ['lotId' => 'b', 'remaining' => 50, 'points' => 50],
            ['lotId' => 'c', 'remaining' => 90, 'points' => 20],
        ], $allocation);
    }

    public function testABatchThatIsNotNeededIsNotTouched(): void
    {
        $allocation = $this->lots->allocate([
            ['id' => 'a', 'remaining' => 80],
            ['id' => 'b', 'remaining' => 20],
        ], 80);

        static::assertCount(1, $allocation);
        static::assertSame('a', $allocation[0]['lotId']);
    }

    public function testTheAllocationCoversLessThanAskedWhenTheBatchesRunOut(): void
    {
        $allocation = $this->lots->allocate([
            ['id' => 'a', 'remaining' => 30],
            ['id' => 'b', 'remaining' => 20],
        ], 100);

        // the caller compares the sum against its request: 50 is less than 100
        static::assertSame(50, array_sum(array_column($allocation, 'points')));
    }

    public function testANonPositiveRequestAllocatesNothing(): void
    {
        static::assertSame([], $this->lots->allocate([['id' => 'a', 'remaining' => 30]], 0));
        static::assertSame([], $this->lots->allocate([['id' => 'a', 'remaining' => 30]], -10));
    }

    public function testNoBatchesAllocateNothing(): void
    {
        static::assertSame([], $this->lots->allocate([], 50));
    }

    public function testABatchWithoutRemainingPointsIsSkipped(): void
    {
        $allocation = $this->lots->allocate([
            ['id' => 'a', 'remaining' => 0],
            ['id' => 'b', 'remaining' => 40],
        ], 10);

        static::assertSame([['lotId' => 'b', 'remaining' => 40, 'points' => 10]], $allocation);
    }

    public function testAGiveBackFollowsTheWithdrawalsInTheirOrder(): void
    {
        $plan = $this->lots->planGiveBack([
            $this->use('u1', 'a', points: 100, remaining: 20),
            $this->use('u2', 'b', points: 50, remaining: 50),
        ], 120);

        static::assertSame([
            ['useId' => 'u1', 'lotId' => 'a', 'returned' => 0, 'remaining' => 20, 'points' => 100],
            ['useId' => 'u2', 'lotId' => 'b', 'returned' => 0, 'remaining' => 50, 'points' => 20],
        ], $plan);
    }

    /**
     * An order can come back in parts: the second return may only give back what the first one left,
     * which is exactly the difference between what was withdrawn and what already came back.
     */
    public function testAGiveBackGivesOnlyWhatIsStillOut(): void
    {
        $plan = $this->lots->planGiveBack([
            $this->use('u1', 'a', points: 100, returned: 80, remaining: 80),
        ], 50);

        static::assertSame([['useId' => 'u1', 'lotId' => 'a', 'returned' => 80, 'remaining' => 80, 'points' => 20]], $plan);
    }

    public function testAWithdrawalThatAlreadyCameBackGivesNothing(): void
    {
        static::assertSame([], $this->lots->planGiveBack([
            $this->use('u1', 'a', points: 100, returned: 100, remaining: 100),
        ], 50));
    }

    /**
     * Points live with their batch: a batch that ran out while the goods were travelling brings
     * nothing back, because the points would arrive already expired.
     */
    public function testADeadBatchBringsNothingBack(): void
    {
        static::assertSame([], $this->lots->planGiveBack([
            $this->use('u1', 'a', points: 100, expiresAt: new \DateTimeImmutable('2026-10-01 00:00:00'), remaining: 0),
        ], 50));

        static::assertSame([], $this->lots->planGiveBack([
            $this->use('u1', 'a', points: 100, expiredAt: new \DateTimeImmutable('2026-10-01 00:00:00'), remaining: 0),
        ], 50));
    }

    public function testABatchWithoutAnExpiryDateGivesBackForever(): void
    {
        $plan = $this->lots->planGiveBack([
            $this->use('u1', 'a', points: 100, expiresAt: null, remaining: 0),
        ], 100);

        static::assertCount(1, $plan);
        static::assertSame(100, $plan[0]['points']);
    }

    public function testAGiveBackOfNothingPlansNothing(): void
    {
        static::assertSame([], $this->lots->planGiveBack([$this->use('u1', 'a', points: 100)], 0));
        static::assertSame([], $this->lots->planGiveBack([], 50));
    }

    /**
     * @return array{
     *     useId: string,
     *     lotId: string,
     *     points: int,
     *     returned: int,
     *     remaining: int,
     *     expiresAt: \DateTimeInterface|null,
     *     expiredAt: \DateTimeInterface|null
     * }
     */
    private function use(
        string $useId,
        string $lotId,
        int $points,
        int $returned = 0,
        int $remaining = 0,
        ?\DateTimeInterface $expiresAt = null,
        ?\DateTimeInterface $expiredAt = null,
    ): array {
        return [
            'useId' => $useId,
            'lotId' => $lotId,
            'points' => $points,
            'returned' => $returned,
            'remaining' => $remaining,
            'expiresAt' => $expiresAt,
            'expiredAt' => $expiredAt,
        ];
    }
}
