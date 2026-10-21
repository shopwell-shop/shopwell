<?php declare(strict_types=1);

namespace Shopwell\Storefront\Page\Account\OrderReturn;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Storefront\Page\PageLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

#[Package('after-sales')]
class GuestOrderReturnPageLoadedEvent extends PageLoadedEvent
{
    /**
     * @internal
     */
    public function __construct(
        protected GuestOrderReturnPage $page,
        SalesChannelContext $salesChannelContext,
        Request $request
    ) {
        parent::__construct($salesChannelContext, $request);
    }

    public function getPage(): GuestOrderReturnPage
    {
        return $this->page;
    }
}
