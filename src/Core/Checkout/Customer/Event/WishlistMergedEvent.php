<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Customer\Event;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Event\ShopwellSalesChannelEvent;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class WishlistMergedEvent extends Event implements ShopwellSalesChannelEvent
{
    /**
     * @param array<array{id: string, productId?: string, productVersionId?: string}> $products
     */
    public function __construct(
        protected array $products,
        protected SalesChannelContext $context
    ) {
    }

    /**
     * @return array<array{id: string, productId?: string, productVersionId?: string}>
     */
    public function getProducts(): array
    {
        return $this->products;
    }

    public function getContext(): Context
    {
        return $this->context->getContext();
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->context;
    }
}
