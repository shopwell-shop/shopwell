<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Product\Events;

use Shopwell\Core\Content\Product\SalesChannel\Sorting\ProductSortingCollection;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Event\NestedEvent;
use Shopwell\Core\Framework\Event\ShopwellSalesChannelEvent;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

#[Package('inventory')]
class ProductListingCollectSortingEvent extends NestedEvent implements ShopwellSalesChannelEvent
{
    public function __construct(
        protected Request $request,
        protected ProductSortingCollection $sortings,
        protected SalesChannelContext $context,
    ) {
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    public function getSortings(): ProductSortingCollection
    {
        return $this->sortings;
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
