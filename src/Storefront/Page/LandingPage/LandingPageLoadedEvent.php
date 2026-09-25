<?php declare(strict_types=1);

namespace Shopwell\Storefront\Page\LandingPage;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Storefront\Page\PageLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

/**
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class LandingPageLoadedEvent extends PageLoadedEvent
{
    public function __construct(
        protected LandingPage $page,
        SalesChannelContext $salesChannelContext,
        Request $request,
    ) {
        parent::__construct($salesChannelContext, $request);
    }

    public function getPage(): LandingPage
    {
        return $this->page;
    }
}
