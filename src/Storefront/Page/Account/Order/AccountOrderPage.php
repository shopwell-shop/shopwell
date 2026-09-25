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
}
