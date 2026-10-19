<?php declare(strict_types=1);

namespace Shopwell\Storefront\Page\Account\Wallet;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Storefront\Page\PageLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class AccountWalletPageLoadedEvent extends PageLoadedEvent
{
    public function __construct(
        protected AccountWalletPage $page,
        SalesChannelContext $salesChannelContext,
        Request $request,
    ) {
        parent::__construct($salesChannelContext, $request);
    }

    public function getPage(): AccountWalletPage
    {
        return $this->page;
    }
}
