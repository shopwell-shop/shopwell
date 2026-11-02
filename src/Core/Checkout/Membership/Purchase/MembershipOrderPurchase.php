<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Membership\Purchase;

use Shopwell\Core\Checkout\Membership\Entity\MembershipOrder\MembershipOrderCollection;
use Shopwell\Core\Checkout\Membership\Entity\MembershipOrder\MembershipOrderEntity;
use Shopwell\Core\Checkout\Membership\Service\MembershipSubscriptionService;
use Shopwell\Core\Checkout\Payment\Storefront\Settlement\SettleablePurchase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\Request\Money;

/**
 * A paid membership order waiting to become a membership on the customer.
 *
 * The row is locked by {@see MembershipOrderPurchaseStore} for the settlement that builds this object,
 * so the membership that is granted and the state that records it cannot be separated by a second
 * notification of the same payment.
 *
 * This class is the bridge between the two: it turns the purchase into a membership (through
 * {@see MembershipSubscriptionService}) and records on the order which period that purchase bought.
 *
 * @final
 */
#[Package('checkout')]
class MembershipOrderPurchase implements SettleablePurchase
{
    /**
     * @internal
     *
     * @param array<string, mixed> $row the locked `membership_order` row
     * @param EntityRepository<MembershipOrderCollection> $orderRepository
     */
    public function __construct(
        private readonly array $row,
        private readonly EntityRepository $orderRepository,
        private readonly MembershipSubscriptionService $subscriptions,
    ) {
    }

    public function purchaseId(): string
    {
        return (string) $this->row['id'];
    }

    public function amountMinor(): int
    {
        return Money::fromFloat((float) $this->row['amount'], (string) $this->row['currency']);
    }

    public function isSettled(): bool
    {
        return $this->row['state'] === MembershipOrderEntity::STATE_ACTIVATED;
    }

    /**
     * A tier that was removed while the payment was open cannot be granted - the order fails visibly
     * instead of taking the money for nothing.
     */
    public function isSettleable(): bool
    {
        return $this->row['loyalty_tier_id'] !== null;
    }

    public function settle(string $paymentNo, \DateTimeInterface $settledAt, Context $context): void
    {
        $subscription = $this->subscriptions->open($this->row, $context);

        // the membership may run longer than this purchase - a renewal extends it - so the period this
        // order bought is read back from its end instead of being taken from the membership
        $expiresAt = $subscription->getEndsAt();

        $this->orderRepository->update([[
            'id' => $this->purchaseId(),
            'state' => MembershipOrderEntity::STATE_ACTIVATED,
            'paymentNo' => $paymentNo,
            'paidAt' => $settledAt,
            'activatedAt' => $settledAt,
            'startsAt' => \DateTimeImmutable::createFromInterface($expiresAt)->modify(\sprintf('-%d days', (int) $this->row['duration_days'])),
            'expiresAt' => $expiresAt,
        ]], $context);
    }

    public function fail(string $paymentNo, \DateTimeInterface $failedAt, Context $context): void
    {
        $this->orderRepository->update([[
            'id' => $this->purchaseId(),
            'state' => MembershipOrderEntity::STATE_FAILED,
            'paymentNo' => $paymentNo,
            'paidAt' => $failedAt,
        ]], $context);
    }
}
