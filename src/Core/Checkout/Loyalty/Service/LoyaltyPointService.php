<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Loyalty\Service;

use Psr\Clock\ClockInterface;
use Shopwell\Core\Checkout\Loyalty\Entity\CustomerLoyalty\CustomerLoyaltyCollection;
use Shopwell\Core\Checkout\Loyalty\Entity\CustomerLoyalty\CustomerLoyaltyEntity;
use Shopwell\Core\Checkout\Loyalty\Event\LoyaltyPointsAwardedEvent;
use Shopwell\Core\Checkout\Loyalty\Event\LoyaltyPointsRedeemedEvent;
use Shopwell\Core\Checkout\Loyalty\LoyaltyException;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * The business rules of the loyalty program: what a point booking means, how long points live and
 * when a booking is allowed.
 *
 * The balances are written by {@see LoyaltyLedger}, never here - this service validates, delegates and
 * announces. Events are only dispatched for bookings that really happened, so a redelivered webhook
 * does not tell the shop twice that points were awarded.
 *
 * @final
 */
#[Package('checkout')]
class LoyaltyPointService
{
    final public const TRANSACTION_TYPE_AWARD = 'award';

    final public const TRANSACTION_TYPE_REDEEM = 'redeem';

    final public const TRANSACTION_TYPE_ADJUST = 'adjust';

    final public const TRANSACTION_TYPE_EXPIRE = 'expire';

    /**
     * @internal
     *
     * @param EntityRepository<CustomerLoyaltyCollection> $customerLoyaltyRepository
     */
    public function __construct(
        private readonly EntityRepository $customerLoyaltyRepository,
        private readonly LoyaltyLedger $ledger,
        private readonly LoyaltySettings $settings,
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

        $this->customerLoyaltyRepository->create([[
            'customerId' => $customerId,
            'pointsBalance' => 0,
            'membershipTierId' => $this->tierResolver->findTierIdForGrowthValue(0, $context),
        ]], $context);
    }

    public function award(string $customerId, int $points, ?string $orderId, string $idempotencyKey, Context $context): void
    {
        if ($points <= 0) {
            return;
        }

        $balanceAfter = $this->ledger->bookPoints(
            customerId: $customerId,
            type: self::TRANSACTION_TYPE_AWARD,
            points: $points,
            context: $context,
            orderId: $orderId,
            idempotencyKey: $idempotencyKey,
            expiresAt: $this->resolveExpiresAt()
        );

        if ($balanceAfter === null) {
            return;
        }

        $this->eventDispatcher->dispatch(new LoyaltyPointsAwardedEvent($customerId, $points, $orderId, $context));
    }

    /**
     * @throws LoyaltyException
     */
    public function redeem(string $customerId, int $points, ?string $orderId, ?string $idempotencyKey, Context $context): void
    {
        if ($points <= 0) {
            throw LoyaltyException::invalidPoints($points);
        }

        $balanceAfter = $this->ledger->bookPoints(
            customerId: $customerId,
            type: self::TRANSACTION_TYPE_REDEEM,
            points: -$points,
            context: $context,
            orderId: $orderId,
            idempotencyKey: $idempotencyKey,
            rejectNegativeBalance: true
        );

        if ($balanceAfter === null) {
            return;
        }

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

        $this->ledger->bookPoints(
            customerId: $customerId,
            type: self::TRANSACTION_TYPE_EXPIRE,
            points: -$points,
            context: $context
        );
    }

    /**
     * Books a manual adjustment by an administrator. The resulting balance must not become negative.
     *
     * @throws LoyaltyException
     */
    public function adjust(string $customerId, int $points, string $comment, ?string $administratorId, Context $context): void
    {
        if ($points === 0) {
            return;
        }

        $this->ledger->bookPoints(
            customerId: $customerId,
            type: self::TRANSACTION_TYPE_ADJUST,
            points: $points,
            context: $context,
            comment: $comment,
            administratorId: $administratorId,
            rejectNegativeBalance: true
        );
    }

    /**
     * Adds the growth value a completed order earned and answers the new value.
     */
    public function addGrowthValue(string $customerId, int $growth, Context $context): int
    {
        return $this->ledger->addGrowthValue($customerId, $growth, $context);
    }

    private function resolveExpiresAt(): ?\DateTimeInterface
    {
        $validityDays = $this->settings->pointsValidityDays();

        if ($validityDays <= 0) {
            return null;
        }

        return $this->clock->now()->modify(\sprintf('+%d days', $validityDays));
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
