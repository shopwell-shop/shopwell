<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Membership\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Psr\Clock\ClockInterface;
use Shopwell\Core\Checkout\Ledger\RowLock;
use Shopwell\Core\Checkout\Loyalty\Entity\CustomerLoyalty\CustomerLoyaltyCollection;
use Shopwell\Core\Checkout\Loyalty\Service\LoyaltyAccountService;
use Shopwell\Core\Checkout\Loyalty\Service\TierResolver;
use Shopwell\Core\Checkout\Membership\Entity\MembershipSubscription\MembershipSubscriptionCollection;
use Shopwell\Core\Checkout\Membership\Entity\MembershipSubscription\MembershipSubscriptionEntity;
use Shopwell\Core\Checkout\Membership\MembershipException;
use Shopwell\Core\Checkout\Membership\MembershipSubscriptionStates;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;

/**
 * The memberships a customer holds: which one runs, how a paid order turns into one, and how one ends.
 *
 * An order is the purchase and never changes; this is the membership that purchase bought. Keeping the
 * two apart is what makes a renewal expressible: the same tier extends the running membership, another
 * tier ends it and starts a new one, and the history of both stays readable.
 *
 * A membership does not always come from an order - an operator can grant one - so the purchase is
 * described by the caller and everything after that is the same. That is why {@see self::open()} and
 * {@see self::grant()} share one implementation: what a membership is must not depend on how it was
 * paid for.
 *
 * The tier and the end of the membership are also written onto the loyalty account
 * (`membership_tier_id`, `membership_valid_until`). Those two columns are a projection of this table
 * and nothing else may write them: the storefront, the administration and the tier resolution all read
 * them without knowing that a membership exists, and a projection only stays correct when there is one
 * writer.
 *
 * @final
 */
#[Package('checkout')]
class MembershipSubscriptionService
{
    private const BATCH_SIZE = 500;

    /**
     * @internal
     *
     * @param EntityRepository<MembershipSubscriptionCollection> $subscriptionRepository
     * @param EntityRepository<CustomerLoyaltyCollection> $customerLoyaltyRepository
     */
    public function __construct(
        private readonly EntityRepository $subscriptionRepository,
        private readonly EntityRepository $customerLoyaltyRepository,
        private readonly LoyaltyAccountService $accounts,
        private readonly TierResolver $tiers,
        private readonly RowLock $rows,
        private readonly Connection $connection,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * Turns a paid membership order into a membership and answers the membership it belongs to.
     *
     * @param array<string, mixed> $order the locked `membership_order` row that was paid
     *
     * @throws MembershipException
     */
    public function open(array $order, Context $context): MembershipSubscriptionEntity
    {
        $tierId = $order['loyalty_tier_id'] === null ? null : (string) $order['loyalty_tier_id'];

        if ($tierId === null) {
            // the tier was deleted while the payment was open; granting nothing and taking the money
            // anyway would be worse than failing the settlement
            throw MembershipException::subscriptionWithoutTier((string) $order['id']);
        }

        return $this->subscribe([
            'customerId' => (string) $order['customer_id'],
            'loyaltyTierId' => $tierId,
            'membershipPlanId' => $order['membership_plan_id'] === null ? null : (string) $order['membership_plan_id'],
            'planName' => $order['plan_name'] === null ? null : (string) $order['plan_name'],
            'amount' => (float) $order['amount'],
            'currency' => $order['currency'] === null ? null : (string) $order['currency'],
            'durationDays' => (int) $order['duration_days'],
            'orderId' => (string) $order['id'],
        ], $context);
    }

    /**
     * Grants a membership without a purchase: a goodwill upgrade, a correction, or a membership that
     * was paid outside the shop. Nothing is charged and no order is named - the rest is a purchase
     * like any other, which is what keeps one answer to "does this extend or replace".
     *
     * @throws MembershipException
     */
    public function grant(string $customerId, string $tierId, int $durationDays, Context $context): MembershipSubscriptionEntity
    {
        if ($durationDays < 1) {
            throw MembershipException::invalidSubscriptionDuration($durationDays);
        }

        return $this->subscribe([
            'customerId' => $customerId,
            'loyaltyTierId' => $tierId,
            'membershipPlanId' => null,
            'planName' => null,
            'amount' => 0.0,
            'currency' => null,
            'durationDays' => $durationDays,
            'orderId' => null,
        ], $context);
    }

    /**
     * The membership that is in force right now, or null when the customer has none.
     *
     * A membership that ran out but was not ended yet is not answered: the customer stopped being a
     * member when the period ended, not when a task noticed it.
     */
    public function current(string $customerId, Context $context): ?MembershipSubscriptionEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('customerId', $customerId),
            new EqualsFilter('state', MembershipSubscriptionStates::STATE_ACTIVE)
        );
        $criteria->addSorting(new FieldSorting('endsAt', FieldSorting::DESCENDING));
        $criteria->setLimit(1);

        $subscription = $this->subscriptionRepository->search($criteria, $context)->getEntities()->first();

        if (!$subscription instanceof MembershipSubscriptionEntity) {
            return null;
        }

        return $subscription->isRunningAt($this->clock->now()) ? $subscription : null;
    }

    /**
     * Ends every membership whose period ran out and answers how many were ended.
     *
     * The ids are read first and every membership is ended in its own transaction, so one that is
     * extended at the very same moment is either still running (and not ended) or already ended (and
     * the extension opens a new one). A re-run finds nothing left to do.
     */
    public function expireDue(Context $context): int
    {
        $now = $this->clock->now();
        $ended = 0;

        do {
            $ids = $this->connection->fetchFirstColumn(
                'SELECT `id` FROM `membership_subscription` WHERE `state` = :state AND `ends_at` <= :now ORDER BY `ends_at` ASC LIMIT ' . self::BATCH_SIZE,
                ['state' => MembershipSubscriptionStates::STATE_ACTIVE, 'now' => $now->format('Y-m-d H:i:s.v')],
                ['state' => ParameterType::STRING, 'now' => ParameterType::STRING]
            );

            foreach ($ids as $id) {
                $ended += $this->expire(Uuid::fromBytesToHex($id), $context) ? 1 : 0;
            }
        } while (\count($ids) === self::BATCH_SIZE);

        return $ended;
    }

    /**
     * Ends a membership ahead of its period - a refund, a goodwill decision, a purchase that has to be
     * taken back. The tier falls back to what the growth value earns, the same way it does on expiry.
     *
     * @throws MembershipException
     */
    public function revoke(string $subscriptionId, Context $context): void
    {
        $this->connection->transactional(function () use ($subscriptionId, $context): void {
            $subscription = $this->rows->lock('membership_subscription', ['id' => $subscriptionId]);

            if ($subscription === null) {
                throw MembershipException::subscriptionNotFound($subscriptionId);
            }

            if ($subscription['state'] !== MembershipSubscriptionStates::STATE_ACTIVE) {
                return;
            }

            $this->end($subscription, MembershipSubscriptionStates::END_REVOKED, $this->clock->now(), $context);
        });
    }

    /**
     * @throws MembershipException
     */
    public function get(string $subscriptionId, Context $context): MembershipSubscriptionEntity
    {
        $subscription = $this->subscriptionRepository->search(new Criteria([$subscriptionId]), $context)->getEntities()->first();

        if (!$subscription instanceof MembershipSubscriptionEntity) {
            throw MembershipException::subscriptionNotFound($subscriptionId);
        }

        return $subscription;
    }

    /**
     * The same tier continues where the running membership ends, so a customer who renews early keeps
     * the days that are already paid. Anything else starts today: another tier replaces the running
     * membership, and the days that were left of it are not carried over - a tier is not a currency.
     *
     * The loyalty account and the running membership are locked for the whole decision, so two
     * settlements of the same customer cannot both extend the same period.
     *
     * @param array{customerId: string, loyaltyTierId: string, membershipPlanId: ?string, planName: ?string, amount: float, currency: ?string, durationDays: int, orderId: ?string} $purchase
     *
     * @throws MembershipException
     */
    private function subscribe(array $purchase, Context $context): MembershipSubscriptionEntity
    {
        $subscriptionId = $this->connection->transactional(function () use ($purchase, $context): string {
            $account = $this->lockAccount($purchase['customerId'], $context);
            $now = $this->clock->now();
            $running = $this->lockRunning($purchase['customerId']);

            if ($running !== null && !$this->isRunning($running, $now)) {
                // it ran out but no task has ended it yet; ending it here keeps the table honest
                $this->end($running, MembershipSubscriptionStates::END_EXPIRED, $now, $context);
                $running = null;
            }

            if ($running !== null && $this->sameTier($running, $purchase['loyaltyTierId'])) {
                $id = (string) $running['id'];
                $endsAt = (new \DateTimeImmutable((string) $running['ends_at']))
                    ->modify(\sprintf('+%d days', $purchase['durationDays']));

                $this->subscriptionRepository->update([[
                    'id' => $id,
                    // what this membership cost in total: every purchase that extended it adds up
                    'amount' => (float) $running['amount'] + $purchase['amount'],
                    'endsAt' => $endsAt,
                    'lastOrderId' => $purchase['orderId'] ?? $running['last_order_id'],
                ]], $context);
            } else {
                if ($running !== null) {
                    $this->end($running, MembershipSubscriptionStates::END_SUPERSEDED, $now, $context);
                }

                $id = Uuid::randomHex();
                $endsAt = $now->modify(\sprintf('+%d days', $purchase['durationDays']));

                $this->subscriptionRepository->create([[
                    'id' => $id,
                    'customerId' => $purchase['customerId'],
                    'membershipPlanId' => $purchase['membershipPlanId'],
                    'loyaltyTierId' => $purchase['loyaltyTierId'],
                    'planName' => $purchase['planName'],
                    'amount' => $purchase['amount'],
                    'currency' => $purchase['currency'],
                    'state' => MembershipSubscriptionStates::STATE_ACTIVE,
                    'startsAt' => $now,
                    'endsAt' => $endsAt,
                    'lastOrderId' => $purchase['orderId'],
                ]], $context);
            }

            $this->customerLoyaltyRepository->update([[
                'id' => $account['id'],
                'membershipTierId' => $purchase['loyaltyTierId'],
                'membershipValidUntil' => $endsAt,
            ]], $context);

            return $id;
        });

        return $this->get($subscriptionId, $context);
    }

    private function expire(string $subscriptionId, Context $context): bool
    {
        return (bool) $this->connection->transactional(function () use ($subscriptionId, $context): bool {
            $subscription = $this->rows->lock('membership_subscription', ['id' => $subscriptionId]);

            // extended by a purchase between the scan and this lock, or already ended by another run
            if ($subscription === null || $subscription['state'] !== MembershipSubscriptionStates::STATE_ACTIVE) {
                return false;
            }

            $now = $this->clock->now();

            if ($this->isRunning($subscription, $now)) {
                return false;
            }

            $this->end($subscription, MembershipSubscriptionStates::END_EXPIRED, $now, $context);

            return true;
        });
    }

    /**
     * Writes the end of a membership and takes the tier away from the loyalty account.
     *
     * The projection is cleared before the tier is resolved again: resolving reads the account, and it
     * has to see that the paid tier is gone to fall back to what the growth value earns.
     *
     * @param array<string, mixed> $subscription the locked row
     */
    private function end(array $subscription, string $reason, \DateTimeInterface $at, Context $context): void
    {
        $this->subscriptionRepository->update([[
            'id' => (string) $subscription['id'],
            'state' => MembershipSubscriptionStates::STATE_ENDED,
            'endedAt' => $at,
            'endReason' => $reason,
        ]], $context);

        $customerId = (string) $subscription['customer_id'];
        $account = $this->rows->lock('customer_loyalty', ['customer_id' => $customerId]);

        if ($account === null) {
            return;
        }

        $this->customerLoyaltyRepository->update([[
            'id' => $account['id'],
            'membershipTierId' => null,
            'membershipValidUntil' => null,
        ]], $context);

        // the tier the growth value earns, dispatched as the change it is
        $this->tiers->resolve($customerId, (int) $account['growth_value'], $context);
    }

    /**
     * @return array<string, mixed>
     */
    private function lockAccount(string $customerId, Context $context): array
    {
        $account = $this->rows->lock('customer_loyalty', ['customer_id' => $customerId]);

        if ($account !== null) {
            return $account;
        }

        $this->accounts->open($customerId, $context);

        return $this->rows->lock('customer_loyalty', ['customer_id' => $customerId])
            ?? throw MembershipException::subscriptionWithoutAccount($customerId);
    }

    /**
     * The membership the next purchase has to deal with: the one that is still open, or the one that
     * ran out and nobody ended yet. Older ones are history and are never touched again.
     *
     * @return array<string, mixed>|null
     */
    private function lockRunning(string $customerId): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM `membership_subscription` WHERE `customer_id` = :customerId AND `state` = :state ORDER BY `ends_at` DESC LIMIT 1 FOR UPDATE',
            ['customerId' => Uuid::fromHexToBytes($customerId), 'state' => MembershipSubscriptionStates::STATE_ACTIVE],
            ['customerId' => ParameterType::BINARY, 'state' => ParameterType::STRING]
        );

        if ($row === false) {
            return null;
        }

        return $this->toHex($row);
    }

    /**
     * @param array<string, mixed> $subscription
     */
    private function isRunning(array $subscription, \DateTimeInterface $now): bool
    {
        return new \DateTimeImmutable((string) $subscription['ends_at']) > $now;
    }

    /**
     * @param array<string, mixed> $subscription
     */
    private function sameTier(array $subscription, string $tierId): bool
    {
        return $subscription['loyalty_tier_id'] !== null && (string) $subscription['loyalty_tier_id'] === $tierId;
    }

    /**
     * The uuid columns are read as bytes by the database; answering them as hex keeps the callers free
     * of the conversion, the same way {@see RowLock} does it.
     *
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function toHex(array $row): array
    {
        foreach ($row as $column => $value) {
            if (\is_string($value) && ($column === 'id' || str_ends_with($column, '_id'))) {
                $row[$column] = Uuid::fromBytesToHex($value);
            }
        }

        return $row;
    }
}
