<?php declare(strict_types=1);

namespace Shopwell\Storefront\Page\Sitemap;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Storefront\Page\PageLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

/**
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class SitemapPageLoadedEvent extends PageLoadedEvent
{
    public function __construct(
        protected SitemapPage $page,
        SalesChannelContext $salesChannelContext,
        Request $request,
    ) {
        parent::__construct($salesChannelContext, $request);
    }

    public function getPage(): SitemapPage
    {
        return $this->page;
    }
}
