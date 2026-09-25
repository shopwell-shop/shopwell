<?php

declare(strict_types=1);

namespace Shopwell\Core\Content\Cookie\Event;

use Shopwell\Core\Content\Cookie\Struct\CookieGroupCollection;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Event\ShopwellSalesChannelEvent;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class CookieGroupCollectEvent implements ShopwellSalesChannelEvent
{
    public function __construct(
        public CookieGroupCollection $cookieGroupCollection,
        public readonly Request $request,
        protected readonly SalesChannelContext $salesChannelContext,
    ) {
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
