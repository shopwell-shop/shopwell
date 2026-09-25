<?php declare(strict_types=1);

namespace Shopwell\Storefront\Page\Address\Detail;

use Shopwell\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Country\CountryCollection;
use Shopwell\Core\System\Salutation\SalutationCollection;
use Shopwell\Storefront\Page\Page;

#[Package('checkout')]
class AddressDetailPage extends Page
{
    protected ?CustomerAddressEntity $address = null;

    protected SalutationCollection $salutations;

    protected CountryCollection $countries;

    public function getAddress(): ?CustomerAddressEntity
    {
        return $this->address;
    }

    public function setAddress(?CustomerAddressEntity $address): void
    {
        $this->address = $address;
    }

    public function getSalutations(): SalutationCollection
    {
        return $this->salutations;
    }

    public function setSalutations(SalutationCollection $salutations): void
    {
        $this->salutations = $salutations;
    }

    public function getCountries(): CountryCollection
    {
        return $this->countries;
    }

    public function setCountries(CountryCollection $countries): void
    {
        $this->countries = $countries;
    }
}
