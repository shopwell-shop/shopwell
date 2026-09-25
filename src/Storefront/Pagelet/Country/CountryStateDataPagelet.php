<?php declare(strict_types=1);

namespace Shopwell\Storefront\Pagelet\Country;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Country\Aggregate\CountryState\CountryStateCollection;
use Shopwell\Storefront\Pagelet\Pagelet;

#[Package('discovery')]
class CountryStateDataPagelet extends Pagelet
{
    protected CountryStateCollection $states;

    public function __construct()
    {
        $this->states = new CountryStateCollection();
    }

    public function getStates(): CountryStateCollection
    {
        return $this->states;
    }

    public function setStates(CountryStateCollection $states): void
    {
        $this->states = $states;
    }
}
