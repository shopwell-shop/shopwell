<?php declare(strict_types=1);

namespace Shopwell\Core\System\Country\Aggregate\CountryCityTranslation;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<CountryCityTranslationEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('fundamentals@discovery')]
class CountryCityTranslationCollection extends EntityCollection
{
    /**
     * @return array<string, string>
     */
    public function getCountryCityIds(): array
    {
        return $this->fmap(static fn (CountryCityTranslationEntity $countryCityTranslation) => $countryCityTranslation->getCountryCityId());
    }

    public function filterByCountryCityId(string $id): self
    {
        return $this->filter(static fn (CountryCityTranslationEntity $countryCityTranslation) => $countryCityTranslation->getCountryCityId() === $id);
    }

    /**
     * @return array<string, string>
     */
    public function getLanguageIds(): array
    {
        return $this->fmap(static fn (CountryCityTranslationEntity $countryCityTranslation) => $countryCityTranslation->getLanguageId());
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(static fn (CountryCityTranslationEntity $countryCityTranslation) => $countryCityTranslation->getLanguageId() === $id);
    }

    public function getApiAlias(): string
    {
        return 'country_city_translation_collection';
    }

    protected function getExpectedClass(): string
    {
        return CountryCityTranslationEntity::class;
    }
}
