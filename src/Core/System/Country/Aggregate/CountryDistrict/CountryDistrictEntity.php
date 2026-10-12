<?php declare(strict_types=1);

namespace Shopwell\Core\System\Country\Aggregate\CountryDistrict;

use Shopwell\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressCollection;
use Shopwell\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Country\Aggregate\CountryCity\CountryCityEntity;
use Shopwell\Core\System\Country\Aggregate\CountryDistrictTranslation\CountryDistrictTranslationCollection;
use Shopwell\Core\System\Country\Aggregate\CountryState\CountryStateEntity;
use Shopwell\Core\System\Country\CountryEntity;

/**
 * @codeCoverageIgnore
 */
#[Package('fundamentals@discovery')]
class CountryDistrictEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    protected string $countryId;

    protected string $countryStateId;

    protected string $countryCityId;

    protected string $code;

    protected ?string $name = null;

    protected int $position;

    protected bool $active;

    protected ?CountryEntity $country = null;

    protected ?CountryStateEntity $countryState = null;

    protected ?CountryCityEntity $countryCity = null;

    protected ?CountryDistrictTranslationCollection $translations = null;

    protected ?CustomerAddressCollection $customerAddresses = null;

    protected ?OrderAddressCollection $orderAddresses = null;

    public function getCountryId(): string
    {
        return $this->countryId;
    }

    public function setCountryId(string $countryId): void
    {
        $this->countryId = $countryId;
    }

    public function getCountryStateId(): string
    {
        return $this->countryStateId;
    }

    public function setCountryStateId(string $countryStateId): void
    {
        $this->countryStateId = $countryStateId;
    }

    public function getCountryCityId(): string
    {
        return $this->countryCityId;
    }

    public function setCountryCityId(string $countryCityId): void
    {
        $this->countryCityId = $countryCityId;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): void
    {
        $this->code = $code;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }

    public function getActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    public function getCountry(): ?CountryEntity
    {
        return $this->country;
    }

    public function setCountry(CountryEntity $country): void
    {
        $this->country = $country;
    }

    public function getCountryState(): ?CountryStateEntity
    {
        return $this->countryState;
    }

    public function setCountryState(CountryStateEntity $countryState): void
    {
        $this->countryState = $countryState;
    }

    public function getCountryCity(): ?CountryCityEntity
    {
        return $this->countryCity;
    }

    public function setCountryCity(CountryCityEntity $countryCity): void
    {
        $this->countryCity = $countryCity;
    }

    public function getTranslations(): ?CountryDistrictTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(CountryDistrictTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }

    public function getCustomerAddresses(): ?CustomerAddressCollection
    {
        return $this->customerAddresses;
    }

    public function setCustomerAddresses(CustomerAddressCollection $customerAddresses): void
    {
        $this->customerAddresses = $customerAddresses;
    }

    public function getOrderAddresses(): ?OrderAddressCollection
    {
        return $this->orderAddresses;
    }

    public function setOrderAddresses(OrderAddressCollection $orderAddresses): void
    {
        $this->orderAddresses = $orderAddresses;
    }
}
