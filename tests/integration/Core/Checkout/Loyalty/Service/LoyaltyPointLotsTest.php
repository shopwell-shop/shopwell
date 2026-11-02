<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Checkout\Loyalty\Service;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Loyalty\Entity\CustomerLoyalty\CustomerLoyaltyCollection;
use Shopwell\Core\Checkout\Loyalty\Entity\CustomerLoyalty\CustomerLoyaltyEntity;
use Shopwell\Core\Checkout\Loyalty\Entity\CustomerPointTransaction\CustomerPointTransactionCollection;
use Shopwell\Core\Checkout\Loyalty\Entity\CustomerPointTransaction\CustomerPointTransactionEntity;
use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyPointLot\LoyaltyPointLotCollection;
use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyPointLot\LoyaltyPointLotEntity;
use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyPointLotUse\LoyaltyPointLotUseCollection;
use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyPointLotUse\LoyaltyPointLotUseEntity;
use Shopwell\Core\Checkout\Loyalty\LoyaltyPointTransactionTypes;
use Shopwell\Core\Checkout\Loyalty\Service\LoyaltyLedger;
use Shopwell\Core\Checkout\Loyalty\Service\LoyaltyPointLots;
use Shopwell\Core\Checkout\Loyalty\Service\LoyaltyPointService;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Test\TestDefaults;

/**
 * The batches against the real database.
 *
 * The unit tests decide which batch gives how much; what is pinned here is that the decision survives
 * the DAL, that the three writes of one booking really land together, and that expiring twice does not
 * book twice - the failure the previous implementation could produce, because it marked the points as
 * expired in a transaction of its own.
 *
 * @internal
 */
#[Package('checkout')]
class LoyaltyPointLotsTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use IntegrationTestBehaviour;

    private Context $context;

    /**
     * @var EntityRepository<LoyaltyPointLotCollection>
     */
    private EntityRepository $lots;

    /**
     * @var EntityRepository<LoyaltyPointLotUseCollection>
     */
    private EntityRepository $uses;

    /**
     * @var EntityRepository<CustomerLoyaltyCollection>
     */
    private EntityRepository $accounts;

    /**
     * @var EntityRepository<CustomerPointTransactionCollection>
     */
    private EntityRepository $bookings;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
        $this->lots = static::getContainer()->get('loyalty_point_lot.repository');
        $this->uses = static::getContainer()->get('loyalty_point_lot_use.repository');
        $this->accounts = static::getContainer()->get('customer_loyalty.repository');
        $this->bookings = static::getContainer()->get('customer_point_transaction.repository');
    }

    public function testSpendingTakesTheBatchThatExpiresFirst(): void
    {
        $customerId = $this->createCustomer();

        $this->ledger()->bookPoints($customerId, 'award', 100, $this->context, expiresAt: new \DateTimeImmutable('+10 days'));
        $this->ledger()->bookPoints($customerId, 'award', 200, $this->context, expiresAt: new \DateTimeImmutable('+30 days'));

        $this->ledger()->bookPoints($customerId, 'redeem', -150, $this->context, rejectNegativeBalance: true);

        static::assertSame([0, 150], $this->remainingOf($customerId));
        static::assertSame(150, $this->accountOf($customerId)->getPointsBalance());

        $uses = $this->usesOf($customerId);
        static::assertCount(2, $uses);
        static::assertSame([100, 50], array_map(static fn ($use): int => $use->getPoints(), $uses));
    }

    /**
     * A manual adjustment has no expiry date, so it is the last thing a customer spends - the points
     * that never disappear are the ones worth keeping.
     */
    public function testABatchWithoutExpiryDateIsSpentLast(): void
    {
        $customerId = $this->createCustomer();

        $this->ledger()->bookPoints($customerId, 'award', 100, $this->context, expiresAt: new \DateTimeImmutable('+1 day'));
        $this->ledger()->bookPoints($customerId, 'adjust', 100, $this->context, comment: 'goodwill');

        $this->ledger()->bookPoints($customerId, 'redeem', -150, $this->context, rejectNegativeBalance: true);

        static::assertSame([0, 50], $this->remainingOf($customerId));
    }

    public function testTheBatchesAlwaysAddUpToTheBalance(): void
    {
        $customerId = $this->createCustomer();

        $this->ledger()->bookPoints($customerId, 'award', 100, $this->context, expiresAt: new \DateTimeImmutable('+10 days'));
        $this->ledger()->bookPoints($customerId, 'award', 40, $this->context, expiresAt: new \DateTimeImmutable('+20 days'));
        $this->ledger()->bookPoints($customerId, 'adjust', -30, $this->context, comment: 'correction', rejectNegativeBalance: true);
        $this->ledger()->bookPoints($customerId, 'redeem', -60, $this->context, rejectNegativeBalance: true);

        static::assertSame(
            $this->accountOf($customerId)->getPointsBalance(),
            $this->lotsService()->remaining($customerId)
        );
    }

    public function testExpiringClearsTheBatchesThatRanOutAndLeavesTheOthers(): void
    {
        $customerId = $this->createCustomer();

        $this->ledger()->bookPoints($customerId, 'award', 100, $this->context, expiresAt: new \DateTimeImmutable('-1 day'));
        $this->ledger()->bookPoints($customerId, 'award', 200, $this->context, expiresAt: new \DateTimeImmutable('+30 days'));

        $expired = $this->service()->expireDue($this->context, new \DateTimeImmutable());

        static::assertGreaterThanOrEqual(100, $expired);
        static::assertSame(200, $this->accountOf($customerId)->getPointsBalance());
        static::assertSame([0, 200], $this->remainingOf($customerId));

        $expiredLots = array_filter($this->lotsOf($customerId), static fn (LoyaltyPointLotEntity $lot): bool => $lot->getExpiredAt() !== null);
        static::assertCount(1, $expiredLots);

        $booking = $this->expiryBookingOf($customerId);
        static::assertSame(-100, $booking->getPoints());
        static::assertSame(200, $booking->getBalanceAfter());
    }

    /**
     * The failure the old task could leave behind: a second run finding the points already marked as
     * expired while the balance never moved. Here the second run finds nothing and books nothing.
     */
    public function testExpiringTwiceDoesNotBookTwice(): void
    {
        $customerId = $this->createCustomer();

        $now = new \DateTimeImmutable();
        $this->ledger()->bookPoints($customerId, 'award', 100, $this->context, expiresAt: $now->modify('-1 day'));

        static::assertSame(100, $this->service()->expireDue($this->context, $now));
        static::assertSame(0, $this->service()->expireDue($this->context, $now));

        static::assertCount(1, $this->expiryBookingsOf($customerId));
        static::assertSame(0, $this->accountOf($customerId)->getPointsBalance());
    }

    /**
     * Points expire with the batch they were earned in, not with the balance: a customer who spent the
     * older batch keeps the newer one, and nothing dies twice.
     */
    public function testSpentPointsDoNotExpireAgain(): void
    {
        $customerId = $this->createCustomer();

        $this->ledger()->bookPoints($customerId, 'award', 100, $this->context, expiresAt: new \DateTimeImmutable('-1 day'));
        $this->ledger()->bookPoints($customerId, 'award', 100, $this->context, expiresAt: new \DateTimeImmutable('+30 days'));

        // spends the batch that is about to expire
        $this->ledger()->bookPoints($customerId, 'redeem', -100, $this->context, rejectNegativeBalance: true);

        static::assertSame(0, $this->service()->expireDue($this->context, new \DateTimeImmutable()));
        static::assertSame(100, $this->accountOf($customerId)->getPointsBalance());
        static::assertCount(0, $this->expiryBookingsOf($customerId));
    }

    /**
     * A return gives the points back into the batch they left and keeps its date: refunding into a
     * fresh batch would hand out an expiry date nobody decided.
     *
     * The ledger books the id of the order without a version, exactly as the redemption does, so the
     * batches are addressed by that id and the test can use the same one.
     */
    public function testAReturnGivesThePointsBackToTheBatchTheyCameFrom(): void
    {
        $customerId = $this->createCustomer();
        $orderId = Uuid::randomHex();

        $this->ledger()->bookPoints($customerId, 'award', 500, $this->context, orderId: Uuid::randomHex(), expiresAt: new \DateTimeImmutable('+30 days'));
        $this->ledger()->bookPoints($customerId, 'redeem', -200, $this->context, orderId: $orderId, rejectNegativeBalance: true);

        static::assertSame([300], $this->remainingOf($customerId));

        static::assertSame(200, $this->ledger()->giveBackPoints($customerId, 'return', $orderId, 200, $this->context, 'RT-1'));

        static::assertSame([500], $this->remainingOf($customerId));
        static::assertSame(500, $this->accountOf($customerId)->getPointsBalance());

        $lot = $this->lotsOf($customerId)[0];
        static::assertNotNull($lot->getExpiresAt());
        static::assertSame(date('Y-m-d', strtotime('+30 days')), $lot->getExpiresAt()->format('Y-m-d'));

        $use = $this->usesOf($customerId)[0];
        static::assertSame(200, $use->getPoints());
        static::assertSame(200, $use->getReturnedPoints());

        // a replay of the same return finds nothing left to give back
        static::assertSame(0, $this->ledger()->giveBackPoints($customerId, 'return', $orderId, 200, $this->context, 'RT-1'));
        static::assertSame(500, $this->accountOf($customerId)->getPointsBalance());
    }

    /**
     * An order can come back in parts: every part gives back its share and never more than what is
     * still out, which is what the counter on the withdrawal is for.
     */
    public function testTwoReturnsOfTheSameOrderGiveBackOnlyWhatIsStillOut(): void
    {
        $customerId = $this->createCustomer();
        $orderId = Uuid::randomHex();

        $this->ledger()->bookPoints($customerId, 'award', 500, $this->context, expiresAt: new \DateTimeImmutable('+30 days'));
        $this->ledger()->bookPoints($customerId, 'redeem', -300, $this->context, orderId: $orderId, rejectNegativeBalance: true);

        static::assertSame(100, $this->ledger()->giveBackPoints($customerId, 'return', $orderId, 100, $this->context, 'RT-1'));
        static::assertSame(100, $this->ledger()->giveBackPoints($customerId, 'return', $orderId, 100, $this->context, 'RT-2'));

        // the third one asks for more than the last 100 that are left
        static::assertSame(100, $this->ledger()->giveBackPoints($customerId, 'return', $orderId, 500, $this->context, 'RT-3'));

        static::assertSame(500, $this->accountOf($customerId)->getPointsBalance());
        static::assertSame(500, $this->lotsService()->remaining($customerId));
        static::assertSame(300, $this->usesOf($customerId)[0]->getReturnedPoints());
    }

    /**
     * Points live with their batch: a batch that ran out while the goods were travelling brings
     * nothing back, because the points would arrive already expired.
     */
    public function testAReturnOfADeadBatchGivesNothingBack(): void
    {
        $customerId = $this->createCustomer();
        $orderId = Uuid::randomHex();

        $this->ledger()->bookPoints($customerId, 'award', 200, $this->context, expiresAt: new \DateTimeImmutable('-1 day'));
        $this->ledger()->bookPoints($customerId, 'redeem', -200, $this->context, orderId: $orderId, rejectNegativeBalance: true);

        static::assertSame(0, $this->ledger()->giveBackPoints($customerId, 'return', $orderId, 200, $this->context, 'RT-1'));
        static::assertSame(0, $this->accountOf($customerId)->getPointsBalance());
    }

    /**
     * Taking back what an order earned walks the batches of that order and stops where they stop -
     * points the customer already spent are not turned into a debt.
     */
    public function testRevokingTakesBackWhatTheOrderStillHolds(): void
    {
        $customerId = $this->createCustomer();
        $orderId = Uuid::randomHex();

        $this->ledger()->bookPoints($customerId, 'award', 400, $this->context, orderId: $orderId, expiresAt: new \DateTimeImmutable('+30 days'));
        $this->ledger()->bookPoints($customerId, 'award', 100, $this->context, orderId: Uuid::randomHex(), expiresAt: new \DateTimeImmutable('+30 days'));

        static::assertSame(400, $this->ledger()->revokeOrderPoints($customerId, 'revoke', $orderId, 1000, $this->context, 'RT-1'));

        static::assertSame(100, $this->accountOf($customerId)->getPointsBalance());
        static::assertSame(100, $this->lotsService()->remaining($customerId));
    }

    public function testRevokingWhatIsSpentTakesNothing(): void
    {
        $customerId = $this->createCustomer();
        $orderId = Uuid::randomHex();

        $this->ledger()->bookPoints($customerId, 'award', 200, $this->context, orderId: $orderId, expiresAt: new \DateTimeImmutable('+30 days'));
        $this->ledger()->bookPoints($customerId, 'redeem', -200, $this->context, rejectNegativeBalance: true);

        static::assertSame(0, $this->ledger()->revokeOrderPoints($customerId, 'revoke', $orderId, 200, $this->context, 'RT-1'));
        static::assertSame(0, $this->accountOf($customerId)->getPointsBalance());
    }

    /**
     * A wallet top-up bonus is points the shop hands out with no order behind them. The batch it opens
     * must therefore live without an order id, be spendable like any other, and the key the caller
     * brings is what keeps a repeated payment notification from paying the bonus twice.
     */
    public function testABonusWithoutAnOrderIsBookedOnceAndCanBeSpent(): void
    {
        $customerId = $this->createCustomer();
        $key = Uuid::randomHex() . '-bonus';

        $this->service()->award($customerId, 300, null, $key, $this->context);
        $this->service()->award($customerId, 300, null, $key, $this->context);

        static::assertSame(300, $this->accountOf($customerId)->getPointsBalance());

        $lot = $this->lotsOf($customerId)[0];
        static::assertNull($lot->getOrderId());
        static::assertSame(300, $lot->getRemainingPoints());

        $bookings = $this->bookingsOf($customerId);
        static::assertCount(1, $bookings);
        static::assertSame($key, $bookings[0]->getIdempotencyKey());
        static::assertNull($bookings[0]->getOrderId());

        $this->ledger()->bookPoints($customerId, 'redeem', -100, $this->context, rejectNegativeBalance: true);

        static::assertSame(200, $this->accountOf($customerId)->getPointsBalance());
        static::assertSame([200], $this->remainingOf($customerId));
    }

    private function ledger(): LoyaltyLedger
    {
        return static::getContainer()->get(LoyaltyLedger::class);
    }

    private function lotsService(): LoyaltyPointLots
    {
        return static::getContainer()->get(LoyaltyPointLots::class);
    }

    private function service(): LoyaltyPointService
    {
        return static::getContainer()->get(LoyaltyPointService::class);
    }

    /**
     * @return list<int> the remaining points of the customer's batches, ascending - the order the
     *                   assertions can name without depending on the ids
     */
    private function remainingOf(string $customerId): array
    {
        $remaining = array_map(
            static fn (LoyaltyPointLotEntity $lot): int => $lot->getRemainingPoints(),
            $this->lotsOf($customerId)
        );

        sort($remaining);

        return $remaining;
    }

    /**
     * @return list<LoyaltyPointLotEntity>
     */
    private function lotsOf(string $customerId): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customerId', $customerId));
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::ASCENDING));

        return array_values($this->lots->search($criteria, $this->context)->getEntities()->getElements());
    }

    /**
     * @return list<LoyaltyPointLotUseEntity>
     */
    private function usesOf(string $customerId): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('lot.customerId', $customerId));

        $uses = array_values($this->uses->search($criteria, $this->context)->getEntities()->getElements());

        usort($uses, static fn ($a, $b): int => $b->getPoints() <=> $a->getPoints());

        return $uses;
    }

    private function accountOf(string $customerId): CustomerLoyaltyEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customerId', $customerId));
        $criteria->setLimit(1);

        $account = $this->accounts->search($criteria, $this->context)->getEntities()->first();
        static::assertInstanceOf(CustomerLoyaltyEntity::class, $account);

        return $account;
    }

    /**
     * @return list<CustomerPointTransactionEntity>
     */
    private function bookingsOf(string $customerId): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customerId', $customerId));
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::ASCENDING));

        return array_values(array_filter(
            $this->bookings->search($criteria, $this->context)->getEntities()->getElements(),
            static fn ($booking): bool => $booking instanceof CustomerPointTransactionEntity
        ));
    }

    private function expiryBookingOf(string $customerId): CustomerPointTransactionEntity
    {
        $bookings = $this->expiryBookingsOf($customerId);
        static::assertCount(1, $bookings);

        return $bookings[0];
    }

    /**
     * @return list<CustomerPointTransactionEntity>
     */
    private function expiryBookingsOf(string $customerId): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('customerId', $customerId),
            new EqualsFilter('type', LoyaltyPointTransactionTypes::EXPIRE)
        );

        return array_values(array_filter(
            $this->bookings->search($criteria, $this->context)->getEntities()->getElements(),
            static fn ($booking): bool => $booking instanceof CustomerPointTransactionEntity
        ));
    }

    private function createCustomer(): string
    {
        $customerId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        static::getContainer()->get('customer.repository')->create([[
            'id' => $customerId,
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
            'defaultShippingAddress' => [
                'id' => $addressId,
                'name' => 'Max Mustermann',
                'street' => 'Musterstraße 1',
                'city' => 'Schöppingen',
                'zipcode' => '12345',
                'gender' => $this->getValidGender(),
                'countryId' => $this->getValidCountryId(),
            ],
            'defaultBillingAddressId' => $addressId,
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'email' => Uuid::randomHex() . '@example.com',
            'password' => TestDefaults::HASHED_PASSWORD,
            'name' => 'Max Mustermann',
            'gender' => $this->getValidGender(),
            'customerNumber' => 'C' . $customerId,
            'languageId' => Defaults::LANGUAGE_SYSTEM,
        ]], $this->context);

        return $customerId;
    }
}
