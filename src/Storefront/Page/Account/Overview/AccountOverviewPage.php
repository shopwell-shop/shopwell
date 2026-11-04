<?php declare(strict_types=1);

namespace Shopwell\Storefront\Page\Account\Overview;

use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Order\OrderCollection;
use Shopwell\Core\Checkout\Order\OrderEntity;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\ArrayStruct;
use Shopwell\Storefront\Page\Page;
use Shopwell\Storefront\Pagelet\Newsletter\Account\NewsletterAccountPagelet;

/**
 * The account dashboard: what is waiting for the customer, what they bought last, and what they hold.
 *
 * Two rules shape this object. The first is that it only carries data the page really shows - a number
 * that is displayed has to come from a route, never from a template doing arithmetic on something else.
 * The second is that a switched-off feature contributes nothing at all: with the wallet or the loyalty
 * programme disabled the matching property stays `null`, so the template has a single, unambiguous
 * signal ("not available") instead of a zero that looks like a real balance.
 */
#[Package('checkout')]
class AccountOverviewPage extends Page
{
    /**
     * How many orders the "recent orders" table shows.
     */
    final public const RECENT_ORDER_LIMIT = 3;

    protected ?OrderEntity $newestOrder = null;

    protected ?OrderCollection $recentOrders = null;

    protected CustomerEntity $customer;

    protected NewsletterAccountPagelet $newsletterAccountPagelet;

    /**
     * Orders that are waiting for the customer to pay.
     */
    protected int $pendingPaymentCount = 0;

    /**
     * Orders that are paid but not finished yet - shipped and on their way, or waiting to ship.
     */
    protected int $pendingDeliveryCount = 0;

    /**
     * Balance of the wallet in the currency the customer is shopping in, or `null` when the shop does
     * not run a wallet.
     */
    protected ?float $walletBalance = null;

    /**
     * The points programme payload, or `null` when the shop does not run one.
     */
    protected ?ArrayStruct $rewards = null;

    /**
     * @var array<string, mixed>|null
     */
    protected ?array $currentTier = null;

    /**
     * @var array<string, mixed>|null
     */
    protected ?array $nextTier = null;

    /**
     * Tier points the customer collected - the number the ladder is measured in, not the redeemable
     * points balance.
     */
    protected int $tierPoints = 0;

    /**
     * Share of the way to the next tier, 0-100.
     */
    protected int $tierProgress = 0;

    /**
     * Tier points still missing for the next tier, `null` when there is no next tier.
     */
    protected ?int $tierPointsToNext = null;

    public function setNewestOrder(OrderEntity $order): void
    {
        $this->newestOrder = $order;
    }

    public function getNewestOrder(): ?OrderEntity
    {
        return $this->newestOrder;
    }

    /**
     * Never answers `null`: a page without orders shows an empty table, not a broken template. The page
     * is built through `createFrom()`, which does not run the constructor, so the fallback lives here.
     */
    public function getRecentOrders(): OrderCollection
    {
        return $this->recentOrders ?? new OrderCollection();
    }

    public function setRecentOrders(OrderCollection $recentOrders): void
    {
        $this->recentOrders = $recentOrders;
    }

    public function getCustomer(): CustomerEntity
    {
        return $this->customer;
    }

    public function setCustomer(CustomerEntity $customer): void
    {
        $this->customer = $customer;
    }

    public function getNewsletterAccountPagelet(): NewsletterAccountPagelet
    {
        return $this->newsletterAccountPagelet;
    }

    public function setNewsletterAccountPagelet(NewsletterAccountPagelet $newsletterAccountPagelet): void
    {
        $this->newsletterAccountPagelet = $newsletterAccountPagelet;
    }

    public function getPendingPaymentCount(): int
    {
        return $this->pendingPaymentCount;
    }

    public function setPendingPaymentCount(int $pendingPaymentCount): void
    {
        $this->pendingPaymentCount = $pendingPaymentCount;
    }

    public function getPendingDeliveryCount(): int
    {
        return $this->pendingDeliveryCount;
    }

    public function setPendingDeliveryCount(int $pendingDeliveryCount): void
    {
        $this->pendingDeliveryCount = $pendingDeliveryCount;
    }

    public function getWalletBalance(): ?float
    {
        return $this->walletBalance;
    }

    public function setWalletBalance(?float $walletBalance): void
    {
        $this->walletBalance = $walletBalance;
    }

    public function getRewards(): ?ArrayStruct
    {
        return $this->rewards;
    }

    public function setRewards(?ArrayStruct $rewards): void
    {
        $this->rewards = $rewards;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getCurrentTier(): ?array
    {
        return $this->currentTier;
    }

    /**
     * @param array<string, mixed>|null $currentTier
     */
    public function setCurrentTier(?array $currentTier): void
    {
        $this->currentTier = $currentTier;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getNextTier(): ?array
    {
        return $this->nextTier;
    }

    /**
     * @param array<string, mixed>|null $nextTier
     */
    public function setNextTier(?array $nextTier): void
    {
        $this->nextTier = $nextTier;
    }

    public function getTierPoints(): int
    {
        return $this->tierPoints;
    }

    public function setTierPoints(int $tierPoints): void
    {
        $this->tierPoints = $tierPoints;
    }

    public function getTierProgress(): int
    {
        return $this->tierProgress;
    }

    public function setTierProgress(int $tierProgress): void
    {
        $this->tierProgress = $tierProgress;
    }

    public function getTierPointsToNext(): ?int
    {
        return $this->tierPointsToNext;
    }

    public function setTierPointsToNext(?int $tierPointsToNext): void
    {
        $this->tierPointsToNext = $tierPointsToNext;
    }
}
