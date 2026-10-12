<?php declare(strict_types=1);

namespace Shopwell\Core\System\Country\Aggregate\CountryDistrictTranslation;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<CountryDistrictTranslationEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('fundamentals@discovery')]
class CountryDistrictTranslationCollection extends EntityCollection
{
    /**
     * @return array<string, string>
     */
    public function getCountryDistrictIds(): array
    {
        return $this->fmap(static fn (CountryDistrictTranslationEntity $countryDistrictTranslation) => $countryDistrictTranslation->getCountryDistrictId());
    }

    public function filterByCountryDistrictId(string $id): self
    {
        return $this->filter(static fn (CountryDistrictTranslationEntity $countryDistrictTranslation) => $countryDistrictTranslation->getCountryDistrictId() === $id);
    }

    /**
     * @return array<string, string>
     */
    public function getLanguageIds(): array
    {
        return $this->fmap(static fn (CountryDistrictTranslationEntity $countryDistrictTranslation) => $countryDistrictTranslation->getLanguageId());
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(static fn (CountryDistrictTranslationEntity $countryDistrictTranslation) => $countryDistrictTranslation->getLanguageId() === $id);
    }

    public function getApiAlias(): string
    {
        return 'country_district_translation_collection';
    }

    protected function getExpectedClass(): string
    {
        return CountryDistrictTranslationEntity::class;
    }
}
