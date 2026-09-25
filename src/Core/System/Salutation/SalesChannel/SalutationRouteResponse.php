<?php declare(strict_types=1);

namespace Shopwell\Core\System\Salutation\SalesChannel;

use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\StoreApiResponse;
use Shopwell\Core\System\Salutation\SalutationCollection;

/**
 * @extends StoreApiResponse<EntitySearchResult<SalutationCollection>>
 */
#[Package('checkout')]
class SalutationRouteResponse extends StoreApiResponse
{
    public function getSalutations(): SalutationCollection
    {
        return $this->object->getEntities();
    }
}
