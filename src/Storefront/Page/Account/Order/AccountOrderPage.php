<?php declare(strict_types=1);

namespace Shopwell\Storefront\Page\Account\Order;

use Shopwell\Core\Checkout\Order\OrderCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Storefront\Page\Page;

#[Package('checkout')]
class AccountOrderPage extends Page
{
    /**
     * @var EntitySearchResult<OrderCollection>
     */
    protected EntitySearchResult $orders;

    protected ?string $deepLinkCode = null;

    /**
     * The order-list tag the customer picked, `all` when the list is unfiltered.
     */
    protected string $orderStateFilter = AccountOrderStateCounter::FILTER_ALL;

    /**
     * How many orders sit behind each tag, keyed by filter, including `all`.
     *
     * @var array<string, int>
     */
    protected array $orderStateCounts = [];

    /**
     * @return EntitySearchResult<OrderCollection>
     */
    public function getOrders(): EntitySearchResult
    {
        return $this->orders;
    }

    /**
     * @param EntitySearchResult<OrderCollection> $orders
     */
    public function setOrders(EntitySearchResult $orders): void
    {
        $this->orders = $orders;
    }

    public function getDeepLinkCode(): ?string
    {
        return $this->deepLinkCode;
    }

    public function setDeepLinkCode(?string $deepLinkCode): void
    {
        $this->deepLinkCode = $deepLinkCode;
    }

    /**
     * The filter in effect, always one of the known tags - the loader maps an unknown request value
     * onto `all` before it gets here.
     */
    public function getOrderStateFilter(): string
    {
        return $this->orderStateFilter;
    }

    public function setOrderStateFilter(string $orderStateFilter): void
    {
        $this->orderStateFilter = $orderStateFilter;
    }

    /**
     * @return array<string, int>
     */
    public function getOrderStateCounts(): array
    {
        return $this->orderStateCounts;
    }

    /**
     * @param array<string, int> $orderStateCounts
     */
    public function setOrderStateCounts(array $orderStateCounts): void
    {
        $this->orderStateCounts = $orderStateCounts;
    }

    /**
     * How many orders the tag stands for - `0` for a tag nobody counted yet.
     */
    public function getOrderStateCount(string $filter): int
    {
        return $this->orderStateCounts[$filter] ?? 0;
    }
}
