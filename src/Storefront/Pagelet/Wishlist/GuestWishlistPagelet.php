<?php declare(strict_types=1);

namespace Shopwell\Storefront\Pagelet\Wishlist;

use Shopwell\Core\Content\Product\SalesChannel\ProductListResponse;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Storefront\Pagelet\Pagelet;

#[Package('discovery')]
class GuestWishlistPagelet extends Pagelet
{
    protected ProductListResponse $searchResult;

    public function getSearchResult(): ProductListResponse
    {
        return $this->searchResult;
    }

    public function setSearchResult(ProductListResponse $searchResult): void
    {
        $this->searchResult = $searchResult;
    }
}
