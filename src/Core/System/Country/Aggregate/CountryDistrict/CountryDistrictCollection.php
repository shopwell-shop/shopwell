<?php declare(strict_types=1);

namespace Shopwell\Core\System\Country\Aggregate\CountryDistrict;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<CountryDistrictEntity>
 */
#[Package('fundamentals@discovery')]
class CountryDistrictCollection extends EntityCollection
{
    /**
     * @return array<string>
     */
    public function getCountryIds(): array
    {
        return $this->fmap(static fn (CountryDistrictEntity $countryDistrict) => $countryDistrict->getCountryId());
    }

    public function filterByCountryId(string $id): self
    {
        return $this->filter(static fn (CountryDistrictEntity $countryDistrict) => $countryDistrict->getCountryId() === $id);
    }

    /**
     * @return array<string>
     */
    public function getCountryCityIds(): array
    {
        return $this->fmap(static fn (CountryDistrictEntity $countryDistrict) => $countryDistrict->getCountryCityId());
    }

    public function filterByCountryCityId(string $id): self
    {
        return $this->filter(static fn (CountryDistrictEntity $countryDistrict) => $countryDistrict->getCountryCityId() === $id);
    }

    public function getApiAlias(): string
    {
        return 'country_district_collection';
    }

    protected function getExpectedClass(): string
    {
        return CountryDistrictEntity::class;
    }
}
