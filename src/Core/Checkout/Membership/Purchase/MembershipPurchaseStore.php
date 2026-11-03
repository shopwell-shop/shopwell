<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Membership\Purchase;

use Shopwell\Core\Checkout\Ledger\RowLock;
use Shopwell\Core\Checkout\Membership\Entity\MembershipPurchase\MembershipPurchaseCollection;
use Shopwell\Core\Checkout\Membership\Service\MembershipSubscriptionService;
use Shopwell\Core\Checkout\Payment\Storefront\Settlement\PurchaseStore;
use Shopwell\Core\Checkout\Payment\Storefront\Settlement\SettleablePurchase;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Log\Package;

/**
 * Locks one membership purchase for the settlement that is about to open its membership.
 *
 * @final
 */
#[Package('checkout')]
class MembershipPurchaseStore implements PurchaseStore
{
    /**
     * @internal
     *
     * @param EntityRepository<MembershipPurchaseCollection> $purchaseRepository
     */
    public function __construct(
        private readonly EntityRepository $purchaseRepository,
        private readonly MembershipSubscriptionService $subscriptions,
        private readonly RowLock $rows,
    ) {
    }

    public function lockForSettlement(string $purchaseId): ?SettleablePurchase
    {
        $row = $this->rows->lock('membership_purchase', ['id' => $purchaseId]);

        if ($row === null) {
            return null;
        }

        return new MembershipPurchase($row, $this->purchaseRepository, $this->subscriptions);
    }
}
