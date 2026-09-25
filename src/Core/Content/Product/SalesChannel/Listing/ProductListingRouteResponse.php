<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Product\SalesChannel\Listing;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<ProductListingResult>
 */
#[Package('inventory')]
class ProductListingRouteResponse extends StoreApiResponse
{
    public function getResult(): ProductListingResult
    {
        return $this->object;
    }
}
