<?php declare(strict_types=1);

namespace Shopwell\Core\System\Country\SalesChannel;

use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Country\Aggregate\CountryCity\CountryCityCollection;
use Shopwell\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<EntitySearchResult<CountryCityCollection>>
 */
#[Package('fundamentals@discovery')]
class CountryCityRouteResponse extends StoreApiResponse
{
    public function getCities(): CountryCityCollection
    {
        return $this->object->getEntities();
    }
}
