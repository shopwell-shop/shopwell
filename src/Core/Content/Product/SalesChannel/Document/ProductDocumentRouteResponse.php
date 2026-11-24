<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Product\SalesChannel\Document;

use Shopwell\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<EntitySearchResult<ProductDocumentCollection>>
 */
#[Package('inventory')]
class ProductDocumentRouteResponse extends StoreApiResponse
{
    /**
     * @return EntitySearchResult<ProductDocumentCollection>
     */
    public function getResult(): EntitySearchResult
    {
        return $this->object;
    }

    public function getDocuments(): ProductDocumentCollection
    {
        return $this->object->getEntities();
    }
}
