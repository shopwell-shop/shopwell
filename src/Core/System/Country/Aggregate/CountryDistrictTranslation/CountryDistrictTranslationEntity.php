<?php declare(strict_types=1);

namespace Shopwell\Core\System\Country\Aggregate\CountryDistrictTranslation;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopwell\Core\Framework\DataAbstractionLayer\TranslationEntity;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Country\Aggregate\CountryDistrict\CountryDistrictEntity;

/**
 * @codeCoverageIgnore
 */
#[Package('fundamentals@discovery')]
class CountryDistrictTranslationEntity extends TranslationEntity
{
    use EntityCustomFieldsTrait;

    protected string $countryDistrictId;

    protected ?string $name = null;

    protected ?CountryDistrictEntity $countryDistrict = null;

    public function getCountryDistrictId(): string
    {
        return $this->countryDistrictId;
    }

    public function setCountryDistrictId(string $countryDistrictId): void
    {
        $this->countryDistrictId = $countryDistrictId;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getCountryDistrict(): ?CountryDistrictEntity
    {
        return $this->countryDistrict;
    }

    public function setCountryDistrict(CountryDistrictEntity $countryDistrict): void
    {
        $this->countryDistrict = $countryDistrict;
    }
}
