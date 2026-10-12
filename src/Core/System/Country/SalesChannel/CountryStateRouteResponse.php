<?php declare(strict_types=1);

namespace Shopwell\Core\System\Country\SalesChannel;

use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Country\Aggregate\CountryState\CountryStateCollection;
use Shopwell\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<EntitySearchResult<CountryStateCollection>>
 */
#[Package('fundamentals@discovery')]
class CountryStateRouteResponse extends StoreApiResponse
{
    protected bool $hasCities = false;

    public function getStates(): CountryStateCollection
    {
        return $this->object->getEntities();
    }

    /**
     * Whether the country provides cities below its states, so address forms can switch
     * to the province-city-district cascade layout.
     */
    public function hasCities(): bool
    {
        return $this->hasCities;
    }

    public function setHasCities(bool $hasCities): void
    {
        $this->hasCities = $hasCities;
    }
}
