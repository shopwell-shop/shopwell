<?php declare(strict_types=1);

namespace Shopwell\Storefront\Page\Account\Login;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Country\CountryCollection;
use Shopwell\Core\System\Dictionary\Aggregate\DictionaryEntry\DictionaryEntryCollection;
use Shopwell\Storefront\Page\Page;

#[Package('checkout')]
class AccountLoginPage extends Page
{
    protected CountryCollection $countries;

    protected DictionaryEntryCollection $genders;

    public function getCountries(): CountryCollection
    {
        return $this->countries;
    }

    public function setCountries(CountryCollection $countries): void
    {
        $this->countries = $countries;
    }

    public function getGenders(): DictionaryEntryCollection
    {
        return $this->genders;
    }

    public function setGenders(DictionaryEntryCollection $genders): void
    {
        $this->genders = $genders;
    }
}
