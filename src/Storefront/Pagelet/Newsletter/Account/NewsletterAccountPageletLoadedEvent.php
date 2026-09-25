<?php declare(strict_types=1);

namespace Shopwell\Storefront\Pagelet\Newsletter\Account;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Storefront\Pagelet\PageletLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class NewsletterAccountPageletLoadedEvent extends PageletLoadedEvent
{
    public function __construct(
        protected NewsletterAccountPagelet $pagelet,
        SalesChannelContext $salesChannelContext,
        Request $request
    ) {
        parent::__construct($salesChannelContext, $request);
    }

    public function getPagelet(): NewsletterAccountPagelet
    {
        return $this->pagelet;
    }
}
