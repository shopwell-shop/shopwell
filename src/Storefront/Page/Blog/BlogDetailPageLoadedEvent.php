<?php declare(strict_types=1);

namespace Shopwell\Storefront\Page\Blog;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Storefront\Page\PageLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

#[Package('discovery')]
class BlogDetailPageLoadedEvent extends PageLoadedEvent
{
    public function __construct(
        protected BlogDetailPage $page,
        SalesChannelContext $salesChannelContext,
        Request $request
    ) {
        parent::__construct($salesChannelContext, $request);
    }

    public function getPage(): BlogDetailPage
    {
        return $this->page;
    }
}
