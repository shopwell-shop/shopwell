<?php declare(strict_types=1);

namespace Shopwell\Core\System\Country\Aggregate\CountryCity;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<CountryCityEntity>
 */
#[Package('fundamentals@discovery')]
class CountryCityCollection extends EntityCollection
{
    /**
     * @return array<string>
     */
    public function getCountryIds(): array
    {
        return $this->fmap(static fn (CountryCityEntity $countryCity) => $countryCity->getCountryId());
    }

    public function filterByCountryId(string $id): self
    {
        return $this->filter(static fn (CountryCityEntity $countryCity) => $countryCity->getCountryId() === $id);
    }

    /**
     * @return array<string>
     */
    public function getCountryStateIds(): array
    {
        return $this->fmap(static fn (CountryCityEntity $countryCity) => $countryCity->getCountryStateId());
    }

    public function filterByCountryStateId(string $id): self
    {
        return $this->filter(static fn (CountryCityEntity $countryCity) => $countryCity->getCountryStateId() === $id);
    }

    public function getApiAlias(): string
    {
        return 'country_city_collection';
    }

    protected function getExpectedClass(): string
    {
        return CountryCityEntity::class;
    }
}
