<?php declare(strict_types=1);

namespace Shopwell\Core\System\Country\SalesChannel;

use Shopwell\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Routing\StoreApiRouteScope;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\System\Country\Aggregate\CountryDistrict\CountryDistrictCollection;
use Shopwell\Core\System\Country\Aggregate\CountryDistrict\CountryDistrictDefinition;
use Shopwell\Core\System\Country\Event\CountryDistrictCriteriaEvent;
use Shopwell\Core\System\Country\Extension\CountryDistrictRouteExtension;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('fundamentals@discovery')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class CountryDistrictRoute
{
    final public const ALL_TAG = 'country-district-route';

    /**
     * @internal
     *
     * @param EntityRepository<CountryDistrictCollection> $countryDistrictRepository
     */
    public function __construct(
        private readonly EntityRepository $countryDistrictRepository,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly CacheTagCollector $cacheTagCollector,
        private readonly ExtensionDispatcher $extensions,
    ) {
    }

    public static function buildName(string $id): string
    {
        return 'country-district-route-' . $id;
    }

    #[Route(
        path: '/store-api/country-district/{countryCityId}',
        name: 'store-api.country.district',
        methods: [Request::METHOD_GET, Request::METHOD_POST],
        defaults: [PlatformRequest::ATTRIBUTE_ENTITY => CountryDistrictDefinition::ENTITY_NAME, PlatformRequest::ATTRIBUTE_HTTP_CACHE => true],
    )]
    public function load(string $countryCityId, Request $request, Criteria $criteria, SalesChannelContext $context): CountryDistrictRouteResponse
    {
        return $this->extensions->publish(
            name: CountryDistrictRouteExtension::NAME,
            extension: new CountryDistrictRouteExtension($countryCityId, $request, $criteria, $context),
            function: $this->_load(...),
        );
    }

    private function _load(string $countryCityId, Request $request, Criteria $criteria, SalesChannelContext $context): CountryDistrictRouteResponse
    {
        $this->cacheTagCollector->addTag(self::buildName($countryCityId), self::ALL_TAG);

        $criteria->addFilter(
            new EqualsFilter('countryCityId', $countryCityId),
            new EqualsFilter('active', true)
        );
        $criteria->addSorting(new FieldSorting('position', FieldSorting::ASCENDING, true));
        $criteria->addSorting(new FieldSorting('name', FieldSorting::ASCENDING));

        $this->dispatcher->dispatch(new CountryDistrictCriteriaEvent($countryCityId, $request, $criteria, $context));
        $countryDistricts = $this->countryDistrictRepository->search($criteria, $context->getContext());

        return new CountryDistrictRouteResponse($countryDistricts);
    }
}
