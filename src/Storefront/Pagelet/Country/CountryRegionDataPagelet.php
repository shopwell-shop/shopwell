<?php declare(strict_types=1);

namespace Shopwell\Storefront\Pagelet\Country;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Country\Aggregate\CountryCity\CountryCityCollection;
use Shopwell\Core\System\Country\Aggregate\CountryDistrict\CountryDistrictCollection;
use Shopwell\Storefront\Pagelet\Pagelet;

/**
 * Carries the administrative divisions below the province level of a country.
 *
 * Only one of both collections is filled per request: the city select loads the cities of the
 * selected province, the district select loads the districts of the selected city.
 */
#[Package('discovery')]
class CountryRegionDataPagelet extends Pagelet
{
    protected CountryCityCollection $cities;

    protected CountryDistrictCollection $districts;

    public function __construct()
    {
        $this->cities = new CountryCityCollection();
        $this->districts = new CountryDistrictCollection();
    }

    public function getCities(): CountryCityCollection
    {
        return $this->cities;
    }

    public function setCities(CountryCityCollection $cities): void
    {
        $this->cities = $cities;
    }

    public function getDistricts(): CountryDistrictCollection
    {
        return $this->districts;
    }

    public function setDistricts(CountryDistrictCollection $districts): void
    {
        $this->districts = $districts;
    }
}
