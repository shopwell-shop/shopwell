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
use Shopwell\Core\System\Country\Aggregate\CountryCity\CountryCityCollection;
use Shopwell\Core\System\Country\Aggregate\CountryCity\CountryCityDefinition;
use Shopwell\Core\System\Country\Event\CountryCityCriteriaEvent;
use Shopwell\Core\System\Country\Extension\CountryCityRouteExtension;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('fundamentals@discovery')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class CountryCityRoute
{
    final public const ALL_TAG = 'country-city-route';

    /**
     * @internal
     *
     * @param EntityRepository<CountryCityCollection> $countryCityRepository
     */
    public function __construct(
        private readonly EntityRepository $countryCityRepository,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly CacheTagCollector $cacheTagCollector,
        private readonly ExtensionDispatcher $extensions,
    ) {
    }

    public static function buildName(string $id): string
    {
        return 'country-city-route-' . $id;
    }

    #[Route(
        path: '/store-api/country-city/{countryStateId}',
        name: 'store-api.country.city',
        methods: [Request::METHOD_GET, Request::METHOD_POST],
        defaults: [PlatformRequest::ATTRIBUTE_ENTITY => CountryCityDefinition::ENTITY_NAME, PlatformRequest::ATTRIBUTE_HTTP_CACHE => true],
    )]
    public function load(string $countryStateId, Request $request, Criteria $criteria, SalesChannelContext $context): CountryCityRouteResponse
    {
        return $this->extensions->publish(
            name: CountryCityRouteExtension::NAME,
            extension: new CountryCityRouteExtension($countryStateId, $request, $criteria, $context),
            function: $this->_load(...),
        );
    }

    private function _load(string $countryStateId, Request $request, Criteria $criteria, SalesChannelContext $context): CountryCityRouteResponse
    {
        $this->cacheTagCollector->addTag(self::buildName($countryStateId), self::ALL_TAG);

        $criteria->addFilter(
            new EqualsFilter('countryStateId', $countryStateId),
            new EqualsFilter('active', true)
        );
        $criteria->addSorting(new FieldSorting('position', FieldSorting::ASCENDING, true));
        $criteria->addSorting(new FieldSorting('name', FieldSorting::ASCENDING));

        $this->dispatcher->dispatch(new CountryCityCriteriaEvent($countryStateId, $request, $criteria, $context));
        $countryCities = $this->countryCityRepository->search($criteria, $context->getContext());

        return new CountryCityRouteResponse($countryCities);
    }
}
