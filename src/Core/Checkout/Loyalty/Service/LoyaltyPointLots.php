<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Loyalty\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Psr\Clock\ClockInterface;
use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyPointLot\LoyaltyPointLotCollection;
use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyPointLotUse\LoyaltyPointLotUseCollection;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;

/**
 * The stock of point batches behind one balance.
 *
 * This class never opens a transaction and never writes the balance. It is the working part of
 * {@see LoyaltyLedger}: the ledger takes the account row lock and wraps the booking, and inside that
 * transaction this class moves the batches. Used on its own the `FOR UPDATE` in here would end with
 * the statement and protect nothing - hence the narrow contract instead of a public service with a
 * life of its own.
 *
 * A booking is two steps on purpose. {@see allocate()} decides which batches give how much and touches
 * nothing; only then does the caller write the point transaction, and {@see commit()} records the
 * result. The order matters: a use row points at the transaction it belongs to, so the transaction has
 * to exist first - while its amount is the outcome of the allocation. Splitting the decision from the
 * write is what lets both be true, and it leaves the decision testable without a database.
 *
 * Spending always takes the batch that expires first. "First" means: a batch with a date before one
 * without, then the earlier date, then the older booking. A manual adjustment has no expiry date at
 * all, so it is spent last - it is the only points that never disappear, and burning them first would
 * be the opposite of what a customer expects.
 *
 * A return walks the same way back. {@see lockUsage()} reads the withdrawals of one order and
 * {@see planGiveBack()} decides which of them get points back, in the same oldest-expiry-first order;
 * the points land in the batch they left and keep its date. A batch that ran out in the meantime
 * brings nothing back: giving points into a dead batch would hand them out already expired, and
 * inventing a new date would be a gift nobody decided to give.
 *
 * The invariant this class keeps is `sum(remaining_points) == points_balance` for every customer.
 * {@see remaining()} exists so a violation can be reported instead of silently drifted past.
 *
 * @internal the batches are a mechanism of {@see LoyaltyLedger}, not a booking API. Points are
 *           awarded, spent and adjusted through {@see LoyaltyPointService}, which keeps the rules
 *           and lets the ledger decide which batch pays.
 */
#[Package('checkout')]
class LoyaltyPointLots
{
    /**
     * @internal
     *
     * @param EntityRepository<LoyaltyPointLotCollection> $lotRepository
     * @param EntityRepository<LoyaltyPointLotUseCollection> $useRepository
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly EntityRepository $lotRepository,
        private readonly EntityRepository $useRepository,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * Books the points a booking added as a new batch.
     *
     * The batch keeps the full amount as `remaining_points`; nothing has been spent from it yet.
     */
    public function deposit(
        string $customerId,
        int $points,
        ?\DateTimeInterface $expiresAt,
        ?string $orderId,
        Context $context,
    ): void {
        $this->lotRepository->create([[
            'customerId' => $customerId,
            'points' => $points,
            'remainingPoints' => $points,
            'expiresAt' => $expiresAt,
            'orderId' => $orderId,
        ]], $context);
    }

    /**
     * Locks the batches of one customer that a booking may take from, oldest expiry first.
     *
     * With a moment given, only the batches that have run out by then are answered - that is what the
     * expiration job drains, and re-reading it under the lock is what makes a second worker see zero
     * remaining instead of expiring the same points twice.
     *
     * @return list<array{id: string, remaining: int}>
     */
    public function lockOpenLots(string $customerId, ?\DateTimeInterface $expiredBy = null): array
    {
        return $this->lock($customerId, $expiredBy, null);
    }

    /**
     * Locks the batches one order created, i.e. the points the order earned and still holds.
     *
     * A return takes back what the order gave - not what the customer owns - so the batches are
     * addressed by the order that opened them and never by "whatever expires next".
     *
     * @return list<array{id: string, remaining: int}>
     */
    public function lockLotsOfOrder(string $customerId, string $orderId): array
    {
        return $this->lock($customerId, null, $orderId);
    }

    /**
     * Decides which of the locked batches give how many points. Touches nothing.
     *
     * Answers fewer entries than the batches when the request is covered earlier, and covers less than
     * requested when the batches run out - the caller compares the sum against what it asked for.
     * `remaining` is the batch's size at allocation time; {@see commit()} subtracts from it, so the
     * caller must not rebuild the allocation from a different read.
     *
     * @param list<array{id: string, remaining: int}> $lots
     *
     * @return list<array{lotId: string, remaining: int, points: int}>
     */
    public function allocate(array $lots, int $points): array
    {
        if ($points <= 0) {
            return [];
        }

        $allocation = [];
        $open = $points;

        foreach ($lots as $lot) {
            if ($open <= 0) {
                break;
            }

            $taken = min($open, $lot['remaining']);

            if ($taken <= 0) {
                continue;
            }

            $allocation[] = ['lotId' => $lot['id'], 'remaining' => $lot['remaining'], 'points' => $taken];
            $open -= $taken;
        }

        return $allocation;
    }

    /**
     * Writes an allocation down: one use row per batch, and the batch shrinks accordingly.
     *
     * With a moment given, the batch is also marked as expired. A batch that runs out keeps its row -
     * the history of what the customer once earned is not something an expiry should erase.
     *
     * @param list<array{lotId: string, remaining: int, points: int}> $allocation
     *
     * @return int the number of points the allocation takes
     */
    public function commit(array $allocation, string $transactionId, ?\DateTimeInterface $expiredAt, Context $context): int
    {
        if ($allocation === []) {
            return 0;
        }

        $uses = [];
        $updates = [];
        $taken = 0;

        foreach ($allocation as $entry) {
            $uses[] = [
                'lotId' => $entry['lotId'],
                'transactionId' => $transactionId,
                'points' => $entry['points'],
                // the dal writes a missing property of a required field as null instead of using the
                // ddl default, so the counter has to be spelled out for every new withdrawal
                'returnedPoints' => 0,
            ];

            $update = [
                'id' => $entry['lotId'],
                'remainingPoints' => $entry['remaining'] - $entry['points'],
            ];

            if ($expiredAt !== null) {
                $update['expiredAt'] = $expiredAt;
            }

            $updates[] = $update;
            $taken += $entry['points'];
        }

        $this->useRepository->create($uses, $context);
        $this->lotRepository->update($updates, $context);

        return $taken;
    }

    /**
     * What the bookings of one type took out of the batches for one order, and how much of that has
     * not been given back yet.
     *
     * Both numbers come from the same read because they belong together: the difference of them is the
     * part a return may still settle, and a return that has nothing left to settle is answered here
     * before it moves any money.
     *
     * @return array{taken: int, outstanding: int}
     */
    public function usageOf(string $orderId, string $type): array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT COALESCE(SUM(uses.`points`), 0) AS `taken`,
                    COALESCE(SUM(uses.`points` - uses.`returned_points`), 0) AS `outstanding`
             FROM `loyalty_point_lot_use` uses
             INNER JOIN `loyalty_point_transaction` booking ON booking.`id` = uses.`transaction_id`
             WHERE booking.`order_id` = :orderId AND booking.`type` = :type',
            ['orderId' => Uuid::fromHexToBytes($orderId), 'type' => $type],
            ['orderId' => ParameterType::BINARY, 'type' => ParameterType::STRING]
        );

        return [
            'taken' => (int) ($row['taken'] ?? 0),
            'outstanding' => (int) ($row['outstanding'] ?? 0),
        ];
    }

    /**
     * Locks the withdrawals of one order that still have points out, oldest-expiry batch first.
     *
     * The rows are locked so a second return of the same order waits for this one and then reads the
     * updated counters - which is what keeps two returns from giving back the same points.
     *
     * @return list<array{
     *     useId: string,
     *     lotId: string,
     *     points: int,
     *     returned: int,
     *     remaining: int,
     *     expiresAt: \DateTimeInterface|null,
     *     expiredAt: \DateTimeInterface|null
     * }>
     */
    public function lockUsage(string $orderId, string $type): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT uses.`id`, uses.`lot_id`, uses.`points`, uses.`returned_points`,
                    batch.`remaining_points`, batch.`expires_at`, batch.`expired_at`
             FROM `loyalty_point_lot_use` uses
             INNER JOIN `loyalty_point_transaction` booking ON booking.`id` = uses.`transaction_id`
             INNER JOIN `loyalty_point_lot` batch ON batch.`id` = uses.`lot_id`
             WHERE booking.`order_id` = :orderId AND booking.`type` = :type
               AND uses.`points` > uses.`returned_points`
             ORDER BY (batch.`expires_at` IS NULL), batch.`expires_at`, batch.`created_at`, uses.`id`
             FOR UPDATE',
            ['orderId' => Uuid::fromHexToBytes($orderId), 'type' => $type],
            ['orderId' => ParameterType::BINARY, 'type' => ParameterType::STRING]
        );

        $uses = [];
        foreach ($rows as $row) {
            $uses[] = [
                'useId' => Uuid::fromBytesToHex((string) $row['id']),
                'lotId' => Uuid::fromBytesToHex((string) $row['lot_id']),
                'points' => (int) $row['points'],
                'returned' => (int) $row['returned_points'],
                'remaining' => (int) $row['remaining_points'],
                'expiresAt' => $this->toDate($row['expires_at']),
                'expiredAt' => $this->toDate($row['expired_at']),
            ];
        }

        return $uses;
    }

    /**
     * Decides how much of what the order took is given back. Touches nothing.
     *
     * The withdrawals are walked in the order {@see lockUsage()} answers them, so a part return gives
     * back the batch that expires first - the same batch the points were taken from. A withdrawal of a
     * batch that has died, or whose points have run out, is skipped: it cannot take points any more.
     *
     * @param list<array{
     *     useId: string,
     *     lotId: string,
     *     points: int,
     *     returned: int,
     *     remaining: int,
     *     expiresAt: \DateTimeInterface|null,
     *     expiredAt: \DateTimeInterface|null
     * }> $uses
     *
     * @return list<array{useId: string, lotId: string, returned: int, remaining: int, points: int}>
     */
    public function planGiveBack(array $uses, int $points): array
    {
        if ($points <= 0) {
            return [];
        }

        $now = $this->clock->now();
        $plan = [];
        $open = $points;

        foreach ($uses as $use) {
            if ($open <= 0) {
                break;
            }

            if ($use['expiredAt'] !== null) {
                continue;
            }

            if ($use['expiresAt'] !== null && $use['expiresAt'] <= $now) {
                continue;
            }

            $outstanding = $use['points'] - $use['returned'];

            if ($outstanding <= 0) {
                continue;
            }

            $given = min($open, $outstanding);

            $plan[] = [
                'useId' => $use['useId'],
                'lotId' => $use['lotId'],
                'returned' => $use['returned'],
                'remaining' => $use['remaining'],
                'points' => $given,
            ];

            $open -= $given;
        }

        return $plan;
    }

    /**
     * Writes a give-back down: the withdrawal records what came back, the batch grows by it.
     *
     * Two withdrawals can belong to the same batch, so the new size of a batch is accumulated here
     * instead of being read again - the plan was made under the lock and stays the truth.
     *
     * @param list<array{useId: string, lotId: string, returned: int, remaining: int, points: int}> $plan
     *
     * @return int the number of points the give-back puts back
     */
    public function giveBack(array $plan, Context $context): int
    {
        if ($plan === []) {
            return 0;
        }

        $uses = [];
        $remaining = [];
        $given = 0;

        foreach ($plan as $entry) {
            $uses[] = [
                'id' => $entry['useId'],
                'returnedPoints' => $entry['returned'] + $entry['points'],
            ];

            $remaining[$entry['lotId']] = ($remaining[$entry['lotId']] ?? $entry['remaining']) + $entry['points'];
            $given += $entry['points'];
        }

        $batches = [];
        foreach ($remaining as $lotId => $points) {
            $batches[] = ['id' => $lotId, 'remainingPoints' => $points];
        }

        $this->useRepository->update($uses, $context);
        $this->lotRepository->update($batches, $context);

        return $given;
    }

    /**
     * The points still sitting in the batches of one customer. Equals their balance unless the data
     * has drifted apart.
     */
    public function remaining(string $customerId): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT COALESCE(SUM(`remaining_points`), 0) FROM `loyalty_point_lot` WHERE `customer_id` = :customerId',
            ['customerId' => Uuid::fromHexToBytes($customerId)],
            ['customerId' => ParameterType::BINARY]
        );
    }

    /**
     * The customers that own at least one batch which has run out, longest overdue first.
     *
     * Answering customers instead of batches keeps the expiry transactional per customer: one broken
     * account cannot take the whole nightly run down with it.
     *
     * @return list<string>
     */
    public function customersWithExpiredLots(\DateTimeInterface $now, int $limit): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT `customer_id`
             FROM `loyalty_point_lot`
             WHERE `remaining_points` > 0 AND `expires_at` IS NOT NULL AND `expires_at` <= :now
             GROUP BY `customer_id`
             ORDER BY MIN(`expires_at`)
             LIMIT ' . max(1, $limit),
            ['now' => $now->format('Y-m-d H:i:s.v')]
        );

        $customerIds = [];
        foreach ($rows as $row) {
            $customerIds[] = Uuid::fromBytesToHex((string) $row['customer_id']);
        }

        return $customerIds;
    }

    /**
     * @return list<array{id: string, remaining: int}>
     */
    private function lock(string $customerId, ?\DateTimeInterface $expiredBy, ?string $orderId): array
    {
        $conditions = [];
        $parameters = ['customerId' => Uuid::fromHexToBytes($customerId)];
        $types = ['customerId' => ParameterType::BINARY];

        if ($expiredBy !== null) {
            $conditions[] = '`expires_at` IS NOT NULL AND `expires_at` <= :expiredBy';
            $parameters['expiredBy'] = $expiredBy->format('Y-m-d H:i:s.v');
            $types['expiredBy'] = ParameterType::STRING;
        }

        if ($orderId !== null) {
            $conditions[] = '`order_id` = :orderId';
            $parameters['orderId'] = Uuid::fromHexToBytes($orderId);
            $types['orderId'] = ParameterType::BINARY;
        }

        $condition = $conditions === [] ? '' : ' AND ' . implode(' AND ', $conditions);

        $rows = $this->connection->fetchAllAssociative(
            'SELECT `id`, `remaining_points`
             FROM `loyalty_point_lot`
             WHERE `customer_id` = :customerId AND `remaining_points` > 0' . $condition . '
             ORDER BY (`expires_at` IS NULL), `expires_at`, `created_at`, `id`
             FOR UPDATE',
            $parameters,
            $types
        );

        $lots = [];
        foreach ($rows as $row) {
            $lots[] = [
                'id' => Uuid::fromBytesToHex((string) $row['id']),
                'remaining' => (int) $row['remaining_points'],
            ];
        }

        return $lots;
    }

    private function toDate(mixed $value): ?\DateTimeInterface
    {
        if ($value === null || $value === '') {
            return null;
        }

        return new \DateTimeImmutable((string) $value);
    }
}
