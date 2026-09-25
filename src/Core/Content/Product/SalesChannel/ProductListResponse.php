<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Product\SalesChannel;

use Shopwell\Core\Content\Product\ProductCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<EntitySearchResult<ProductCollection>>
 */
#[Package('inventory')]
class ProductListResponse extends StoreApiResponse
{
    public function getProducts(): ProductCollection
    {
        return $this->object->getEntities();
    }
}
