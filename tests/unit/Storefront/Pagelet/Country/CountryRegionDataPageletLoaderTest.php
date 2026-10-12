<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Storefront\Pagelet\Country;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Country\Aggregate\CountryCity\CountryCityCollection;
use Shopwell\Core\System\Country\Aggregate\CountryCity\CountryCityDefinition;
use Shopwell\Core\System\Country\Aggregate\CountryCity\CountryCityEntity;
use Shopwell\Core\System\Country\Aggregate\CountryDistrict\CountryDistrictCollection;
use Shopwell\Core\System\Country\Aggregate\CountryDistrict\CountryDistrictDefinition;
use Shopwell\Core\System\Country\Aggregate\CountryDistrict\CountryDistrictEntity;
use Shopwell\Core\System\Country\SalesChannel\CountryCityRoute;
use Shopwell\Core\System\Country\SalesChannel\CountryCityRouteResponse;
use Shopwell\Core\System\Country\SalesChannel\CountryDistrictRoute;
use Shopwell\Core\System\Country\SalesChannel\CountryDistrictRouteResponse;
use Shopwell\Core\Test\Generator;
use Shopwell\Storefront\Pagelet\Country\CountryRegionDataPageletCriteriaEvent;
use Shopwell\Storefront\Pagelet\Country\CountryRegionDataPageletLoadedEvent;
use Shopwell\Storefront\Pagelet\Country\CountryRegionDataPageletLoader;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(CountryRegionDataPageletLoader::class)]
class CountryRegionDataPageletLoaderTest extends TestCase
{
    public function testLoadCities(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();
        $request = new Request();
        $countryStateId = Uuid::randomHex();

        $cities = new CountryCityCollection([
            (new CountryCityEntity())->assign(['id' => Uuid::randomHex(), 'name' => '市辖区']),
        ]);

        $cityRoute = $this->createMock(CountryCityRoute::class);
        $cityRoute->expects($this->once())
            ->method('load')
            ->with(
                $countryStateId,
                $request,
                static::isInstanceOf(Criteria::class),
                $salesChannelContext,
            )
            ->willReturn(new CountryCityRouteResponse(new EntitySearchResult(
                CountryCityDefinition::ENTITY_NAME,
                1,
                $cities,
                null,
                new Criteria(),
                $salesChannelContext->getContext(),
            )));

        $districtRoute = $this->createMock(CountryDistrictRoute::class);
        $districtRoute->expects($this->never())->method('load');

        $dispatched = [];
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(static function (object $event) use (&$dispatched): object {
                $dispatched[] = $event;

                return $event;
            });

        $loader = new CountryRegionDataPageletLoader($cityRoute, $districtRoute, $eventDispatcher);
        $pagelet = $loader->loadCities($countryStateId, $request, $salesChannelContext);

        static::assertSame($cities, $pagelet->getCities());
        static::assertCount(0, $pagelet->getDistricts());

        static::assertInstanceOf(CountryRegionDataPageletCriteriaEvent::class, $dispatched[0]);
        static::assertInstanceOf(CountryRegionDataPageletLoadedEvent::class, $dispatched[1]);
        static::assertSame($pagelet, $dispatched[1]->getPagelet());
    }

    public function testLoadDistricts(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();
        $request = new Request();
        $countryCityId = Uuid::randomHex();

        $districts = new CountryDistrictCollection([
            (new CountryDistrictEntity())->assign(['id' => Uuid::randomHex(), 'name' => '东城区']),
        ]);

        $cityRoute = $this->createMock(CountryCityRoute::class);
        $cityRoute->expects($this->never())->method('load');

        $districtRoute = $this->createMock(CountryDistrictRoute::class);
        $districtRoute->expects($this->once())
            ->method('load')
            ->with(
                $countryCityId,
                $request,
                static::isInstanceOf(Criteria::class),
                $salesChannelContext,
            )
            ->willReturn(new CountryDistrictRouteResponse(new EntitySearchResult(
                CountryDistrictDefinition::ENTITY_NAME,
                1,
                $districts,
                null,
                new Criteria(),
                $salesChannelContext->getContext(),
            )));

        $dispatched = [];
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(static function (object $event) use (&$dispatched): object {
                $dispatched[] = $event;

                return $event;
            });

        $loader = new CountryRegionDataPageletLoader($cityRoute, $districtRoute, $eventDispatcher);
        $pagelet = $loader->loadDistricts($countryCityId, $request, $salesChannelContext);

        static::assertSame($districts, $pagelet->getDistricts());
        static::assertCount(0, $pagelet->getCities());

        static::assertInstanceOf(CountryRegionDataPageletCriteriaEvent::class, $dispatched[0]);
        static::assertInstanceOf(CountryRegionDataPageletLoadedEvent::class, $dispatched[1]);
        static::assertSame($pagelet, $dispatched[1]->getPagelet());
    }

    public function testLoadCitiesReturnsPageletEvenWithoutCities(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();

        $cityRoute = $this->createMock(CountryCityRoute::class);
        $cityRoute->expects($this->once())
            ->method('load')
            ->willReturn(new CountryCityRouteResponse(new EntitySearchResult(
                CountryCityDefinition::ENTITY_NAME,
                0,
                new CountryCityCollection(),
                null,
                new Criteria(),
                $salesChannelContext->getContext(),
            )));

        $loader = new CountryRegionDataPageletLoader(
            $cityRoute,
            static::createStub(CountryDistrictRoute::class),
            static::createStub(EventDispatcherInterface::class),
        );

        $pagelet = $loader->loadCities(Uuid::randomHex(), new Request(), $salesChannelContext);

        static::assertCount(0, $pagelet->getCities());
        static::assertCount(0, $pagelet->getDistricts());
    }
}
