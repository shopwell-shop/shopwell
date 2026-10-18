<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Loyalty\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\ParameterType;
use Psr\Clock\ClockInterface;
use Shopwell\Core\Checkout\Loyalty\Entity\CustomerLoyalty\CustomerLoyaltyCollection;
use Shopwell\Core\Checkout\Loyalty\Entity\CustomerPointTransaction\CustomerPointTransactionCollection;
use Shopwell\Core\Checkout\Loyalty\LoyaltyException;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;

/**
 * The only writer of a customer loyalty account.
 *
 * Points, growth value and the membership of a customer live on one row, and every one of them is a
 * projection: points from the point ledger, growth from the completed orders, the membership from the
 * purchases. All three are written here inside one transaction on a locked row, because the previous
 * read-modify-write spread over several services lost one of two concurrent bookings - and a lost
 * point booking is a customer complaint nobody can reconstruct afterwards.
 *
 * The ledger stays free of business rules that belong to a caller: it does not decide how many points
 * an order earns or which tier a growth value unlocks. It refuses an overdraft, because only the
 * balance it locked can be judged safely, and it keeps the point booking idempotent by the key the
 * caller brings.
 *
 * @final
 */
#[Package('checkout')]
class LoyaltyLedger
{
    /**
     * @internal
     *
     * @param EntityRepository<CustomerLoyaltyCollection> $customerLoyaltyRepository
     * @param EntityRepository<CustomerPointTransactionCollection> $pointTransactionRepository
     */
    public function __construct(
        private readonly EntityRepository $customerLoyaltyRepository,
        private readonly EntityRepository $pointTransactionRepository,
        private readonly Connection $connection,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * Books one point entry and answers the balance after it.
     *
     * A booking that carries an idempotency key is written once: the key is unique, and a repeated
     * call - a redelivered webhook, a retried message - answers the current balance instead of booking
     * a second time.
     *
     * @throws LoyaltyException when the booking would take the balance below zero
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

            $balanceAfter = $account['pointsBalance'] + $points;

            if ($rejectNegativeBalance && $balanceAfter < 0) {
                throw LoyaltyException::insufficientPoints($customerId, abs($points), $account['pointsBalance']);
            }

            $this->pointTransactionRepository->create([[
                'customerId' => $customerId,
                'type' => $type,
                'points' => $points,
                'balanceAfter' => $balanceAfter,
                'idempotencyKey' => $idempotencyKey,
                'orderId' => $orderId,
                'expiresAt' => $expiresAt,
                'comment' => $comment,
                'administratorId' => $administratorId,
            ]], $context);

            $this->customerLoyaltyRepository->update([[
                'id' => $account['id'],
                'pointsBalance' => $balanceAfter,
            ]], $context);

            return $balanceAfter;
        });
    }

    /**
     * Adds the growth value an order earned and answers the new value. Growth is not part of the point
     * ledger - it never gets spent - so it is only a counter, but it is counted under the same lock for
     * the same reason.
     */
    public function addGrowthValue(string $customerId, int $growth, Context $context): int
    {
        return $this->connection->transactional(function () use ($customerId, $growth, $context): int {
            $account = $this->lockAccount($customerId, $context);
            $growthValue = $account['growthValue'] + $growth;

            $this->customerLoyaltyRepository->update([[
                'id' => $account['id'],
                'growthValue' => $growthValue,
            ]], $context);

            return $growthValue;
        });
    }

    /**
     * Puts a purchased membership on the customer and answers the period it runs.
     *
     * A renewal of the very same tier continues where the running membership ends - a customer who
     * renews early must not lose the days that are already paid. Everything else starts now: buying a
     * different tier replaces the current one, and the paid remainder of the old one is not silently
     * swallowed into the new period.
     *
     * The row is locked while it is read and written, so two settlements of the same purchase cannot
     * both extend the membership.
     *
     * @return array{startsAt: \DateTimeInterface, expiresAt: \DateTimeInterface}
     */
    public function activateMembership(string $customerId, string $tierId, int $durationDays, Context $context): array
    {
        return $this->connection->transactional(function () use ($customerId, $tierId, $durationDays, $context): array {
            $account = $this->lockAccount($customerId, $context);

            $now = $this->clock->now();
            $runningUntil = $account['membershipValidUntil'] !== null
                ? new \DateTimeImmutable($account['membershipValidUntil'])
                : null;

            $continues = $runningUntil !== null && $runningUntil > $now && $account['membershipTierId'] === $tierId;

            $startsAt = $continues ? $runningUntil : $now;
            $expiresAt = $startsAt->modify(\sprintf('+%d days', $durationDays));

            $this->customerLoyaltyRepository->update([[
                'id' => $account['id'],
                'membershipTierId' => $tierId,
                'membershipValidUntil' => $expiresAt,
            ]], $context);

            return ['startsAt' => $startsAt, 'expiresAt' => $expiresAt];
        });
    }

    /**
     * @return array{id: string, pointsBalance: int, growthValue: int, membershipTierId: ?string, membershipValidUntil: ?string}
     */
    private function lockAccount(string $customerId, Context $context): array
    {
        $account = $this->selectForUpdate($customerId);

        if ($account !== null) {
            return $account;
        }

        $id = Uuid::randomHex();

        try {
            $this->customerLoyaltyRepository->create([[
                'id' => $id,
                'customerId' => $customerId,
                'pointsBalance' => 0,
                'growthValue' => 0,
            ]], $context);
        } catch (UniqueConstraintViolationException) {
            // a parallel booking opened the very same account first, its row is the one to book onto
        }

        return $this->selectForUpdate($customerId)
            ?? ['id' => $id, 'pointsBalance' => 0, 'growthValue' => 0, 'membershipTierId' => null, 'membershipValidUntil' => null];
    }

    /**
     * @return array{id: string, pointsBalance: int, growthValue: int, membershipTierId: ?string, membershipValidUntil: ?string}|null
     */
    private function selectForUpdate(string $customerId): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT LOWER(HEX(`id`)) AS `id`, `points_balance`, `growth_value`,
                    LOWER(HEX(`membership_tier_id`)) AS `membership_tier_id`, `membership_valid_until`
             FROM `customer_loyalty`
             WHERE `customer_id` = :customerId
             FOR UPDATE',
            ['customerId' => Uuid::fromHexToBytes($customerId)],
            ['customerId' => ParameterType::BINARY]
        );

        if (!\is_array($row)) {
            return null;
        }

        return [
            'id' => (string) $row['id'],
            'pointsBalance' => (int) $row['points_balance'],
            'growthValue' => (int) $row['growth_value'],
            'membershipTierId' => $row['membership_tier_id'] === null ? null : (string) $row['membership_tier_id'],
            'membershipValidUntil' => $row['membership_valid_until'] === null ? null : (string) $row['membership_valid_until'],
        ];
    }

    private function hasPointTransaction(string $idempotencyKey): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT 1 FROM `customer_point_transaction` WHERE `idempotency_key` = :key LIMIT 1',
            ['key' => $idempotencyKey]
        );
    }
}
