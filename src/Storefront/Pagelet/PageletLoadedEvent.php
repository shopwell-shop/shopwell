<?php declare(strict_types=1);

namespace Shopwell\Storefront\Pagelet;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Event\NestedEvent;
use Shopwell\Core\Framework\Event\ShopwellSalesChannelEvent;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @codeCoverageIgnore
 */
#[Package('discovery')]
abstract class PageletLoadedEvent extends NestedEvent implements ShopwellSalesChannelEvent
{
    public function __construct(
        protected SalesChannelContext $salesChannelContext,
        protected Request $request,
    ) {
    }

    /**
     * @return Pagelet
     */
    abstract public function getPagelet();

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }

    public function getContext(): Context
    {
        return $this->salesChannelContext->getContext();
    }

    public function getRequest(): Request
    {
        return $this->request;
    }
}
