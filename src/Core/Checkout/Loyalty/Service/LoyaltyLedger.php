<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Loyalty\Service;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Checkout\Ledger\RowLock;
use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyAccount\LoyaltyAccountCollection;
use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyPointTransaction\LoyaltyPointTransactionCollection;
use Shopwell\Core\Checkout\Loyalty\LoyaltyException;
use Shopwell\Core\Checkout\Loyalty\LoyaltyPointTransactionTypes;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;

/**
 * The only writer of the points and the tier points of a customer.
 *
 * Three things describe the same points and are therefore written together: the booking (what
 * happened), the balance (the sum today) and the batches (which points those are). Spread over three
 * transactions, a crash in between leaves a booking without its balance or points that belong to no
 * batch - and both are only noticeable when a customer complains. They live in one transaction on a
 * locked account row, and a second concurrent booking waits for that lock instead of overwriting it.
 *
 * The batches are moved by {@see LoyaltyPointLots}; this class only decides the order of the writes,
 * because the use rows of an allocation point at the booking they belong to.
 *
 * The ledger stays free of business rules that belong to a caller: it does not decide how many points
 * an order earns, which tier tier points unlock, or how much of a return is paid out in points.
 * It refuses an overdraft, because only the balance it locked can be judged safely, and it keeps the
 * point booking idempotent by the key the caller brings.
 *
 * The paid membership is not its business either. `tier_id` and `membership_paid_until`
 * are written by {@see \Shopwell\Core\Checkout\Membership\Service\MembershipSubscriptionService}
 * while a membership runs, and `tier_id` is also what {@see TierResolver} keeps up to date
 * with the tier the tier points reach - the column is the effective tier, the date is the paid
 * period, and neither is a balance this class owns.
 *
 * @internal the write order and the row lock are the whole point of this class and cannot be varied
 *           by a caller. Bookings go through {@see LoyaltyPointService}, which keeps the rules and
 *           calls this; a booking kind that service cannot express is an extension point to be added
 *           there, not a reason to call the ledger directly.
 */
#[Package('checkout')]
class LoyaltyLedger
{
    /**
     * @internal
     *
     * @param EntityRepository<LoyaltyAccountCollection> $loyaltyAccountRepository
     * @param EntityRepository<LoyaltyPointTransactionCollection> $pointTransactionRepository
     */
    public function __construct(
        private readonly EntityRepository $loyaltyAccountRepository,
        private readonly EntityRepository $pointTransactionRepository,
        private readonly Connection $connection,
        private readonly RowLock $rows,
        private readonly LoyaltyPointLots $lots,
    ) {
    }

    /**
     * Books one point entry and answers the balance after it.
     *
     * A positive amount opens a batch that expires when the caller says so. A negative amount is taken
     * from the batches that expire first, so the booking records the points the customer actually
     * still owned rather than a number derived from the balance.
     *
     * A booking that carries an idempotency key is written once: the key is unique, and a repeated
     * call - a redelivered webhook, a retried message - answers the current balance instead of booking
     * a second time.
     *
     * @throws LoyaltyException when the booking would take the balance below zero, or when the batches
     *                          do not add up to the balance it locked
     *
     * @return int|null the balance after the booking, or null when the key was already booked
     */
    public function bookPoints(
        string $customerId,
        string $type,
        int $points,
        Context $context,
        ?string $orderId = null,
        ?string $idempotencyKey = null,
        ?string $comment = null,
        ?string $administratorId = null,
        ?\DateTimeInterface $expiresAt = null,
        bool $rejectNegativeBalance = false,
    ): ?int {
        return $this->connection->transactional(function () use (
            $customerId,
            $type,
            $points,
            $context,
            $orderId,
            $idempotencyKey,
            $comment,
            $administratorId,
            $expiresAt,
            $rejectNegativeBalance
        ): ?int {
            $account = $this->lockAccount($customerId, $context);

            if ($idempotencyKey !== null && $this->hasPointTransaction($idempotencyKey)) {
                return null;
            }

            if ($rejectNegativeBalance && $account['pointsBalance'] + $points < 0) {
                throw LoyaltyException::insufficientPoints($customerId, abs($points), $account['pointsBalance']);
            }

            $transactionId = Uuid::randomHex();
            $allocation = [];
            $booked = $points;

            if ($points < 0) {
                $allocation = $this->lots->allocate($this->lots->lockOpenLots($customerId), -$points);
                $booked = -array_sum(array_column($allocation, 'points'));

                // the balance said the points were there, the batches say otherwise: the two have drifted
                if ($booked !== $points) {
                    throw LoyaltyException::lotBalanceMismatch($customerId, $this->lots->remaining($customerId), $account['pointsBalance']);
                }
            }

            $balanceAfter = $account['pointsBalance'] + $booked;

            $this->record($account, $transactionId, $customerId, $type, $booked, $balanceAfter, $context, [
                'orderId' => $orderId,
                'idempotencyKey' => $idempotencyKey,
                'expiresAt' => $expiresAt,
                'comment' => $comment,
                'administratorId' => $administratorId,
            ]);

            // the batch is opened after the booking for the same reason the use rows come after it:
            // everything that points at the booking has to exist second
            if ($booked > 0) {
                $this->lots->deposit($customerId, $booked, $expiresAt, $orderId, $context);
            }

            $this->lots->commit($allocation, $transactionId, null, $context);

            return $balanceAfter;
        });
    }

    /**
     * Expires every batch of one customer that has run out by the given moment.
     *
     * The batches are re-read under the account lock, so a second worker arriving for the same
     * customer finds nothing left instead of expiring the same points twice. The booking, the drained
     * batches and the new balance are one transaction: the half-done state the previous implementation
     * could leave behind - points marked as expired but still spendable - cannot happen here.
     *
     * Answering the expired amount lets the caller sum up the night's work; a customer whose batches do
     * not add up to their balance is reported instead of being quietly skipped.
     *
     * @throws LoyaltyException when the batches do not add up to the balance
     *
     * @return int the number of points expired
     */
    public function expireLots(string $customerId, string $type, \DateTimeInterface $now, Context $context): int
    {
        return $this->connection->transactional(function () use ($customerId, $type, $now, $context): int {
            $account = $this->lockAccount($customerId, $context);

            $lotTotal = $this->lots->remaining($customerId);

            if ($lotTotal !== $account['pointsBalance']) {
                throw LoyaltyException::lotBalanceMismatch($customerId, $lotTotal, $account['pointsBalance']);
            }

            $lots = $this->lots->lockOpenLots($customerId, $now);

            $due = 0;
            foreach ($lots as $lot) {
                $due += $lot['remaining'];
            }

            if ($due === 0) {
                return 0;
            }

            $allocation = $this->lots->allocate($lots, $due);
            $transactionId = Uuid::randomHex();

            $this->record($account, $transactionId, $customerId, $type, -$due, $account['pointsBalance'] - $due, $context);
            $this->lots->commit($allocation, $transactionId, $now, $context);

            return $due;
        });
    }

    /**
     * Gives back the points an order spent, into the batches they were taken from.
     *
     * This is the return of a paid-with-points order: the customer gets the discount back as points,
     * not as money, because that is the form it was taken in. The batches are re-read under the account
     * lock and only the withdrawals that are still out are touched, so a return that comes in parts
     * settles each part once - a repeated call finds nothing left and answers 0.
     *
     * The caller passes points, not money: what a point is worth is the caller's rule, and this class
     * must not learn how a discount was calculated.
     *
     * @return int the number of points given back
     */
    public function giveBackPoints(
        string $customerId,
        string $type,
        string $orderId,
        int $points,
        Context $context,
        ?string $comment = null,
    ): int {
        if ($points <= 0) {
            return 0;
        }

        return $this->connection->transactional(function () use ($customerId, $type, $orderId, $points, $context, $comment): int {
            $account = $this->lockAccount($customerId, $context);

            $plan = $this->lots->planGiveBack(
                $this->lots->lockUsage($orderId, LoyaltyPointTransactionTypes::REDEEM),
                $points
            );

            if ($plan === []) {
                // every batch of the order has either been given back or has died in the meantime
                return 0;
            }

            $given = array_sum(array_column($plan, 'points'));
            $transactionId = Uuid::randomHex();

            $this->record($account, $transactionId, $customerId, $type, $given, $account['pointsBalance'] + $given, $context, [
                'orderId' => $orderId,
                'comment' => $comment,
            ]);

            $this->lots->giveBack($plan, $context);

            return $given;
        });
    }

    /**
     * Takes back the points an order earned, as far as its batches still hold them.
     *
     * The batches are the limit on purpose: points the customer already spent are not turned into a
     * debt, because a negative balance is either impossible to spend or a claim nobody agreed to. What
     * is still there goes, what is gone stays gone.
     *
     * @return int the number of points taken back
     */
    public function revokeOrderPoints(
        string $customerId,
        string $type,
        string $orderId,
        int $points,
        Context $context,
        ?string $comment = null,
    ): int {
        if ($points <= 0) {
            return 0;
        }

        return $this->connection->transactional(function () use ($customerId, $type, $orderId, $points, $context, $comment): int {
            $account = $this->lockAccount($customerId, $context);

            $allocation = $this->lots->allocate($this->lots->lockLotsOfOrder($customerId, $orderId), $points);
            $taken = array_sum(array_column($allocation, 'points'));

            if ($taken === 0) {
                return 0;
            }

            $transactionId = Uuid::randomHex();

            $this->record($account, $transactionId, $customerId, $type, -$taken, $account['pointsBalance'] - $taken, $context, [
                'orderId' => $orderId,
                'comment' => $comment,
            ]);

            $this->lots->commit($allocation, $transactionId, null, $context);

            return $taken;
        });
    }

    /**
     * Adds the tier points an order earned and answers the new total. Tier points are not part of the
     * point ledger - they are never spent - so they are only a counter, but they are counted under the
     * same lock for the same reason.
     */
    public function addTierPoints(string $customerId, int $earned, Context $context): int
    {
        return $this->connection->transactional(function () use ($customerId, $earned, $context): int {
            $account = $this->lockAccount($customerId, $context);
            $tierPoints = $account['tierPoints'] + $earned;

            $this->loyaltyAccountRepository->update([[
                'id' => $account['id'],
                'tierPoints' => $tierPoints,
            ]], $context);

            return $tierPoints;
        });
    }

    /**
     * Writes the booking and the balance it results in - the two rows every booking has.
     *
     * The columns a type does not use are absent rather than empty, so a booking whose caller brought
     * no order does not pretend to belong to one. The batches are not touched here: they are moved by
     * {@see LoyaltyPointLots} around this call, inside the same transaction.
     *
     * @param array{id: string, pointsBalance: int, tierPoints: int} $account
     * @param array<string, mixed> $columns orderId, idempotencyKey, expiresAt, comment, administratorId
     */
    private function record(
        array $account,
        string $transactionId,
        string $customerId,
        string $type,
        int $points,
        int $balanceAfter,
        Context $context,
        array $columns = [],
    ): void {
        $this->pointTransactionRepository->create([array_filter([
            'id' => $transactionId,
            'customerId' => $customerId,
            'type' => $type,
            'points' => $points,
            'balanceAfter' => $balanceAfter,
        ] + $columns, static fn (mixed $value): bool => $value !== null)], $context);

        $this->loyaltyAccountRepository->update([[
            'id' => $account['id'],
            'pointsBalance' => $balanceAfter,
        ]], $context);
    }

    /**
     * @return array{id: string, pointsBalance: int, tierPoints: int}
     */
    private function lockAccount(string $customerId, Context $context): array
    {
        $account = $this->rows->lock('loyalty_account', ['customer_id' => $customerId]);

        if ($account !== null) {
            return $this->toAccount($account);
        }

        $id = Uuid::randomHex();

        $this->rows->createUnlessExists($this->loyaltyAccountRepository, [
            'id' => $id,
            'customerId' => $customerId,
            'pointsBalance' => 0,
            'tierPoints' => 0,
        ], $context);

        $account = $this->rows->lock('loyalty_account', ['customer_id' => $customerId]) ?? [
            'id' => $id,
            'points_balance' => 0,
            'tier_points' => 0,
        ];

        return $this->toAccount($account);
    }

    /**
     * @param array<string, mixed> $account
     *
     * @return array{id: string, pointsBalance: int, tierPoints: int}
     */
    private function toAccount(array $account): array
    {
        return [
            'id' => (string) $account['id'],
            'pointsBalance' => (int) $account['points_balance'],
            'tierPoints' => (int) $account['tier_points'],
        ];
    }

    private function hasPointTransaction(string $idempotencyKey): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT 1 FROM `loyalty_point_transaction` WHERE `idempotency_key` = :key LIMIT 1',
            ['key' => $idempotencyKey]
        );
    }
}
