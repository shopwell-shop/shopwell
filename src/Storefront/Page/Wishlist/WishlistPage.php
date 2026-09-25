<?php declare(strict_types=1);

namespace Shopwell\Storefront\Page\Wishlist;

use Shopwell\Core\Checkout\Customer\SalesChannel\LoadWishlistRouteResponse;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Storefront\Page\Page;

#[Package('discovery')]
class WishlistPage extends Page
{
    protected LoadWishlistRouteResponse $wishlist;

    public function getWishlist(): LoadWishlistRouteResponse
    {
        return $this->wishlist;
    }

    public function setWishlist(LoadWishlistRouteResponse $wishlist): void
    {
        $this->wishlist = $wishlist;
    }
}
