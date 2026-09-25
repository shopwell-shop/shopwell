<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Cart\Event;

use Shopwell\Core\Checkout\Order\OrderEntity;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Event\ShopwellSalesChannelEvent;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Allows the manipulation of the sales channel context after it was assembled from the order
 *
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class SalesChannelContextAssembledEvent extends Event implements ShopwellSalesChannelEvent
{
    /**
     * @internal
     */
    public function __construct(
        private readonly OrderEntity $order,
        private readonly SalesChannelContext $salesChannelContext,
    ) {
    }

    public function getOrder(): OrderEntity
    {
        return $this->order;
    }

    public function getContext(): Context
    {
        return $this->salesChannelContext->getContext();
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }
}
