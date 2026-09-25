<?php declare(strict_types=1);

namespace Shopwell\Storefront\Page\Checkout\Register;

use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Country\CountryCollection;
use Shopwell\Core\System\Salutation\SalutationCollection;
use Shopwell\Storefront\Page\Page;

#[Package('checkout')]
class CheckoutRegisterPage extends Page
{
    protected CountryCollection $countries;

    protected ?CustomerAddressEntity $address = null;

    protected SalutationCollection $salutations;

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

    public function getSalutations(): SalutationCollection
    {
        return $this->salutations;
    }

    public function setSalutations(SalutationCollection $salutations): void
    {
        $this->salutations = $salutations;
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
