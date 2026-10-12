<?php declare(strict_types=1);

namespace Shopwell\Storefront\Pagelet\Country;

use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Country\SalesChannel\CountryCityRoute;
use Shopwell\Core\System\Country\SalesChannel\CountryDistrictRoute;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Do not use direct or indirect repository calls in a PageletLoader. Always use a store-api route to get or put data.
 */
#[Package('discovery')]
class CountryRegionDataPageletLoader
{
    /**
     * @internal
     */
    public function __construct(
        private readonly CountryCityRoute $countryCityRoute,
        private readonly CountryDistrictRoute $countryDistrictRoute,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    /**
     * Loads the cities of a province.
     */
    public function loadCities(string $countryStateId, Request $request, SalesChannelContext $context): CountryRegionDataPagelet
    {
        $pagelet = new CountryRegionDataPagelet();

        $criteria = new Criteria();

        $this->eventDispatcher->dispatch(new CountryRegionDataPageletCriteriaEvent($criteria, $context, $request));

        $response = $this->countryCityRoute->load($countryStateId, $request, $criteria, $context);

        $pagelet->setCities($response->getCities());

        $this->eventDispatcher->dispatch(new CountryRegionDataPageletLoadedEvent($pagelet, $context, $request));

        return $pagelet;
    }

    /**
     * Loads the districts of a city.
     */
    public function loadDistricts(string $countryCityId, Request $request, SalesChannelContext $context): CountryRegionDataPagelet
    {
        $pagelet = new CountryRegionDataPagelet();

        $criteria = new Criteria();

        $this->eventDispatcher->dispatch(new CountryRegionDataPageletCriteriaEvent($criteria, $context, $request));

        $response = $this->countryDistrictRoute->load($countryCityId, $request, $criteria, $context);

        $pagelet->setDistricts($response->getDistricts());

        $this->eventDispatcher->dispatch(new CountryRegionDataPageletLoadedEvent($pagelet, $context, $request));

        return $pagelet;
    }
}
