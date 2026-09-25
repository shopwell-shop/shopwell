<?php declare(strict_types=1);

namespace Shopwell\Storefront\Page\Account\Order;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Storefront\Page\PageLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class AccountEditOrderPageLoadedEvent extends PageLoadedEvent
{
    public function __construct(
        protected AccountEditOrderPage $page,
        SalesChannelContext $salesChannelContext,
        Request $request,
    ) {
        parent::__construct($salesChannelContext, $request);
    }

    public function getPage(): AccountEditOrderPage
    {
        return $this->page;
    }
}
