<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Loyalty\Service;

use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Shopwell\Core\Checkout\Loyalty\Event\LoyaltyPointsAwardedEvent;
use Shopwell\Core\Checkout\Loyalty\Event\LoyaltyPointsRedeemedEvent;
use Shopwell\Core\Checkout\Loyalty\LoyaltyException;
use Shopwell\Core\Checkout\Loyalty\LoyaltyPointTransactionTypes;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * The business rules of the loyalty program: what a point booking means, how long points live and
 * when a booking is allowed.
 *
 * The balances and the batches behind them are written by {@see LoyaltyLedger}, never here - this
 * service validates, delegates and announces. Events are only dispatched for bookings that really
 * happened, so a redelivered webhook does not tell the shop twice that points were awarded.
 *
 * What a return does to the points of an order is a rule of its own and lives in
 * {@see LoyaltyReturnPoints}: it moves points in both directions and touches the batches by hand,
 * which is why it is not another method here.
 *
 * Expiry is the one place where this class loops over a set of customers, and it is deliberate: the
 * ledger can only expire one customer atomically, so the job that expires many has to decide what
 * happens when one of them is broken. Skipping that account and carrying on is the answer - keeping
 * the whole night's run back because one balance drifted would leave every other customer's points
 * unexpired, and the broken one is reported every night until somebody fixes it.
 *
 * Reading the account is {@see LoyaltyAccountService}'s job; this class only changes it.
 *
 * @final
 */
#[Package('checkout')]
class LoyaltyPointService
{
    /**
     * How many customers one run of the expiration job handles. Large enough for a normal night,
     * small enough that a stuck account is reported within the run instead of after it.
     */
    private const EXPIRY_BATCH_SIZE = 500;

    /**
     * @internal
     */
    public function __construct(
        private readonly LoyaltyLedger $ledger,
        private readonly LoyaltyPointLots $lots,
        private readonly LoyaltySettings $settings,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ClockInterface $clock,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function award(string $customerId, int $points, ?string $orderId, string $idempotencyKey, Context $context): void
    {
        if ($points <= 0) {
            return;
        }

        $balanceAfter = $this->ledger->bookPoints(
            customerId: $customerId,
            type: LoyaltyPointTransactionTypes::AWARD,
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
            type: LoyaltyPointTransactionTypes::REDEEM,
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
     * Expires every batch that has run out and answers how many points that freed.
     *
     * Called by the daily task. Each customer is one transaction, so a crash halfway leaves the
     * customers before it expired and the ones after it untouched - never a booking that says
     * "expired" while the points are still spendable.
     *
     * @return int the number of points expired in this run
     */
    public function expireDue(Context $context, ?\DateTimeInterface $now = null): int
    {
        $now ??= $this->clock->now();

        $expired = 0;

        foreach ($this->lots->customersWithExpiredLots($now, self::EXPIRY_BATCH_SIZE) as $customerId) {
            try {
                $expired += $this->ledger->expireLots($customerId, LoyaltyPointTransactionTypes::EXPIRE, $now, $context);
            } catch (LoyaltyException $exception) {
                $this->logger->error('Could not expire the points of a customer.', [
                    'customerId' => $customerId,
                    'exception' => $exception,
                ]);
            }
        }

        return $expired;
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
            type: LoyaltyPointTransactionTypes::ADJUST,
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
}
