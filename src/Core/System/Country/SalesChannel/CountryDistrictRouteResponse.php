<?php declare(strict_types=1);

namespace Shopwell\Core\System\Country\SalesChannel;

use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Country\Aggregate\CountryDistrict\CountryDistrictCollection;
use Shopwell\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<EntitySearchResult<CountryDistrictCollection>>
 */
#[Package('fundamentals@discovery')]
class CountryDistrictRouteResponse extends StoreApiResponse
{
    public function getDistricts(): CountryDistrictCollection
    {
        return $this->object->getEntities();
    }
}
