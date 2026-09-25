<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Product\SalesChannel\CrossSelling;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<CrossSellingElementCollection>
 */
#[Package('inventory')]
class ProductCrossSellingRouteResponse extends StoreApiResponse
{
    public function getResult(): CrossSellingElementCollection
    {
        return $this->object;
    }
}
