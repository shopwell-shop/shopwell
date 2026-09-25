<?php declare(strict_types=1);

namespace Shopwell\Storefront\Pagelet\Wishlist;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Storefront\Pagelet\PageletLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

/**
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class GuestWishlistPageletLoadedEvent extends PageletLoadedEvent
{
    public function __construct(
        protected GuestWishlistPagelet $pagelet,
        SalesChannelContext $salesChannelContext,
        Request $request,
    ) {
        parent::__construct($salesChannelContext, $request);
    }

    public function getPagelet(): GuestWishlistPagelet
    {
        return $this->pagelet;
    }
}
