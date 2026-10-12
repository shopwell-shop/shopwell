<?php declare(strict_types=1);

namespace Shopwell\Core\System\Country\Aggregate\CountryCityTranslation;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopwell\Core\Framework\DataAbstractionLayer\TranslationEntity;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Country\Aggregate\CountryCity\CountryCityEntity;

/**
 * @codeCoverageIgnore
 */
#[Package('fundamentals@discovery')]
class CountryCityTranslationEntity extends TranslationEntity
{
    use EntityCustomFieldsTrait;

    protected string $countryCityId;

    protected ?string $name = null;

    protected ?CountryCityEntity $countryCity = null;

    public function getCountryCityId(): string
    {
        return $this->countryCityId;
    }

    public function setCountryCityId(string $countryCityId): void
    {
        $this->countryCityId = $countryCityId;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getCountryCity(): ?CountryCityEntity
    {
        return $this->countryCity;
    }

    public function setCountryCity(CountryCityEntity $countryCity): void
    {
        $this->countryCity = $countryCity;
    }
}
