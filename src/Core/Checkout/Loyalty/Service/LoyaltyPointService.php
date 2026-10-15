<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Loyalty\Service;

use Psr\Clock\ClockInterface;
use Shopwell\Core\Checkout\Loyalty\Entity\CustomerLoyalty\CustomerLoyaltyCollection;
use Shopwell\Core\Checkout\Loyalty\Entity\CustomerLoyalty\CustomerLoyaltyEntity;
use Shopwell\Core\Checkout\Loyalty\Entity\CustomerPointTransaction\CustomerPointTransactionCollection;
use Shopwell\Core\Checkout\Loyalty\Event\LoyaltyPointsAwardedEvent;
use Shopwell\Core\Checkout\Loyalty\Event\LoyaltyPointsRedeemedEvent;
use Shopwell\Core\Checkout\Loyalty\LoyaltyException;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SystemConfig\SystemConfigService;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @final
 */
#[Package('checkout')]
class LoyaltyPointService
{
    final public const TRANSACTION_TYPE_AWARD = 'award';

    final public const TRANSACTION_TYPE_REDEEM = 'redeem';

    final public const TRANSACTION_TYPE_ADJUST = 'adjust';

    final public const TRANSACTION_TYPE_EXPIRE = 'expire';

    final public const CONFIG_KEY_POINTS_VALIDITY_DAYS = 'core.loyalty.pointsValidityDays';

    /**
     * @internal
     *
     * @param EntityRepository<CustomerPointTransactionCollection> $pointTransactionRepository
     * @param EntityRepository<CustomerLoyaltyCollection> $customerLoyaltyRepository
     */
    public function __construct(
        private readonly EntityRepository $pointTransactionRepository,
        private readonly EntityRepository $customerLoyaltyRepository,
        private readonly SystemConfigService $systemConfigService,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ClockInterface $clock,
        private readonly TierResolver $tierResolver
    ) {
    }

    public function getBalance(string $customerId, Context $context): int
    {
        return $this->findLoyalty($customerId, $context)?->getPointsBalance() ?? 0;
    }

    /**
     * Opens the membership of a customer with a zero balance and the tier a growth value of zero
     * unlocks, so a customer owns a membership from the moment it exists instead of from its first
     * booking. A membership that is already there is left untouched - including its balance and its
     * tier - which makes the call safe to repeat.
     */
    public function provisionMembership(string $customerId, Context $context): void
    {
        if ($this->findLoyalty($customerId, $context) !== null) {
            return;
        }

        $this->createMembership($customerId, 0, $context);
    }

    public function award(string $customerId, int $points, ?string $orderId, string $idempotencyKey, Context $context): void
    {
        if ($points <= 0 || $this->hasTransaction($idempotencyKey, $context)) {
            return;
        }

        $this->book(
            $customerId,
            self::TRANSACTION_TYPE_AWARD,
            $points,
            $orderId,
            $idempotencyKey,
            null,
            null,
            $context
        );

        $this->eventDispatcher->dispatch(new LoyaltyPointsAwardedEvent($customerId, $points, $orderId, $context));
    }

    public function redeem(string $customerId, int $points, ?string $orderId, ?string $idempotencyKey, Context $context): void
    {
        if ($points <= 0) {
            throw LoyaltyException::invalidPoints($points);
        }

        if ($idempotencyKey !== null && $this->hasTransaction($idempotencyKey, $context)) {
            return;
        }

        $balance = $this->getBalance($customerId, $context);

        if ($balance < $points) {
            throw LoyaltyException::insufficientPoints($customerId, $points, $balance);
        }

        $this->book($customerId, self::TRANSACTION_TYPE_REDEEM, -$points, $orderId, $idempotencyKey, null, null, $context);

        $this->eventDispatcher->dispatch(new LoyaltyPointsRedeemedEvent($customerId, $points, $orderId, $context));
    }

    /**
     * Books an expiry of previously awarded points. Used by the scheduled expiration task.
     */
    public function expire(string $customerId, int $points, Context $context): void
    {
        if ($points <= 0) {
            return;
        }

        $this->book($customerId, self::TRANSACTION_TYPE_EXPIRE, -$points, null, null, null, null, $context);
    }

    /**
     * Books a manual adjustment by an administrator. The resulting balance must not become negative.
     */
    public function adjust(string $customerId, int $points, string $comment, ?string $administratorId, Context $context): void
    {
        if ($points === 0) {
            return;
        }

        $balance = $this->getBalance($customerId, $context);

        if ($balance + $points < 0) {
            throw LoyaltyException::insufficientPoints($customerId, abs($points), $balance);
        }

        $this->book($customerId, self::TRANSACTION_TYPE_ADJUST, $points, null, null, $comment, $administratorId, $context);
    }

    private function book(
        string $customerId,
        string $type,
        int $points,
        ?string $orderId,
        ?string $idempotencyKey,
        ?string $comment,
        ?string $administratorId,
        Context $context
    ): void {
        $loyalty = $this->findLoyalty($customerId, $context);

        $balance = $loyalty?->getPointsBalance() ?? 0;
        $balanceAfter = $balance + $points;

        $this->pointTransactionRepository->create([[
            'customerId' => $customerId,
            'type' => $type,
            'points' => $points,
            'balanceAfter' => $balanceAfter,
            'idempotencyKey' => $idempotencyKey,
            'orderId' => $orderId,
            'expiresAt' => $type === self::TRANSACTION_TYPE_AWARD ? $this->resolveExpiresAt($context) : null,
            'comment' => $comment,
            'administratorId' => $administratorId,
        ]], $context);

        if ($loyalty === null) {
            $this->createMembership($customerId, $balanceAfter, $context);

            return;
        }

        $this->customerLoyaltyRepository->update([[
            'id' => $loyalty->getId(),
            'pointsBalance' => $balanceAfter,
        ]], $context);
    }

    /**
     * A membership starts with the balance it is opened with, a growth value of zero and the tier
     * that zero growth unlocks - the tier a customer moves on from with its first order. The tier is
     * assigned here as well and not only in {@see TierResolver::resolve()}, so that a membership
     * always carries the tier its growth value entitles the customer to.
     */
    private function createMembership(string $customerId, int $pointsBalance, Context $context): void
    {
        $this->customerLoyaltyRepository->create([[
            'customerId' => $customerId,
            'pointsBalance' => $pointsBalance,
            'membershipTierId' => $this->tierResolver->findTierIdForGrowthValue(0, $context),
        ]], $context);
    }

    private function resolveExpiresAt(Context $context): ?\DateTimeInterface
    {
        $validityDays = $this->systemConfigService->getInt(self::CONFIG_KEY_POINTS_VALIDITY_DAYS);

        if ($validityDays <= 0) {
            return null;
        }

        return $this->clock->now()->modify(\sprintf('+%d days', $validityDays));
    }

    private function hasTransaction(string $idempotencyKey, Context $context): bool
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('idempotencyKey', $idempotencyKey));
        $criteria->setLimit(1);

        return $this->pointTransactionRepository->searchIds($criteria, $context)->getTotal() > 0;
    }

    private function findLoyalty(string $customerId, Context $context): ?CustomerLoyaltyEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customerId', $customerId));
        $criteria->setLimit(1);

        $loyalty = $this->customerLoyaltyRepository->search($criteria, $context)->getEntities()->first();

        return $loyalty instanceof CustomerLoyaltyEntity ? $loyalty : null;
    }
}
