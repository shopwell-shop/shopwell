<?php declare(strict_types=1);

namespace Shopwell\Storefront\Page\Account\OrderReturn;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Storefront\Page\PageLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

#[Package('after-sales')]
class AccountOrderReturnPageLoadedEvent extends PageLoadedEvent
{
    /**
     * @internal
     */
    public function __construct(
        protected AccountOrderReturnPage $page,
        SalesChannelContext $salesChannelContext,
        Request $request
    ) {
        parent::__construct($salesChannelContext, $request);
    }

    public function getPage(): AccountOrderReturnPage
    {
        return $this->page;
    }
}
