<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Membership\Purchase;

use Shopwell\Core\Checkout\Ledger\RowLock;
use Shopwell\Core\Checkout\Membership\Entity\MembershipOrder\MembershipOrderCollection;
use Shopwell\Core\Checkout\Membership\Service\MembershipSubscriptionService;
use Shopwell\Core\Checkout\Payment\Storefront\Settlement\PurchaseStore;
use Shopwell\Core\Checkout\Payment\Storefront\Settlement\SettleablePurchase;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Log\Package;

/**
 * Locks one membership order for the settlement that is about to open its membership.
 *
 * @final
 */
#[Package('checkout')]
class MembershipOrderPurchaseStore implements PurchaseStore
{
    /**
     * @internal
     *
     * @param EntityRepository<MembershipOrderCollection> $orderRepository
     */
    public function __construct(
        private readonly EntityRepository $orderRepository,
        private readonly MembershipSubscriptionService $subscriptions,
        private readonly RowLock $rows,
    ) {
    }

    public function lockForSettlement(string $purchaseId): ?SettleablePurchase
    {
        $row = $this->rows->lock('membership_order', ['id' => $purchaseId]);

        if ($row === null) {
            return null;
        }

        return new MembershipOrderPurchase($row, $this->orderRepository, $this->subscriptions);
    }
}
