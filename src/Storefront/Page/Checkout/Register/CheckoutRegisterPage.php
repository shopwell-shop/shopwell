<?php declare(strict_types=1);

namespace Shopwell\Storefront\Page\Checkout\Register;

use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Country\CountryCollection;
use Shopwell\Core\System\Dictionary\Aggregate\DictionaryEntry\DictionaryEntryCollection;
use Shopwell\Storefront\Page\Page;

#[Package('checkout')]
class CheckoutRegisterPage extends Page
{
    protected CountryCollection $countries;

    protected ?CustomerAddressEntity $address = null;

    protected DictionaryEntryCollection $genders;

    protected Cart $cart;

    public function getCountries(): CountryCollection
    {
        return $this->countries;
    }

    public function setCountries(CountryCollection $countries): void
    {
        $this->countries = $countries;
    }

    public function getAddress(): ?CustomerAddressEntity
    {
        return $this->address;
    }

    public function setAddress(CustomerAddressEntity $address): void
    {
        $this->address = $address;
    }

    public function getGenders(): DictionaryEntryCollection
    {
        return $this->genders;
    }

    public function setGenders(DictionaryEntryCollection $genders): void
    {
        $this->genders = $genders;
    }

    public function getCart(): Cart
    {
        return $this->cart;
    }

    public function setCart(Cart $cart): void
    {
        $this->cart = $cart;
    }
}
