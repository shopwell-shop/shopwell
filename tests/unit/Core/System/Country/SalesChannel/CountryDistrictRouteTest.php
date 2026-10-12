<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Country\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopwell\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Country\Aggregate\CountryDistrict\CountryDistrictCollection;
use Shopwell\Core\System\Country\Aggregate\CountryDistrict\CountryDistrictDefinition;
use Shopwell\Core\System\Country\Aggregate\CountryDistrict\CountryDistrictEntity;
use Shopwell\Core\System\Country\Event\CountryDistrictCriteriaEvent;
use Shopwell\Core\System\Country\Extension\CountryDistrictRouteExtension;
use Shopwell\Core\System\Country\SalesChannel\CountryDistrictRoute;
use Shopwell\Core\System\Country\SalesChannel\CountryDistrictRouteResponse;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\System\SalesChannel\SalesChannelEntity;
use Shopwell\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('fundamentals@discovery')]
#[CoversClass(CountryDistrictRoute::class)]
class CountryDistrictRouteTest extends TestCase
{
    private SalesChannelContext $salesChannelContext;

    protected function setUp(): void
    {
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId(Uuid::randomHex());

        $this->salesChannelContext = Generator::generateSalesChannelContext(
            baseContext: new Context(new SalesChannelApiSource(Uuid::randomHex())),
            salesChannel: $salesChannel
        );
    }

    public function testBuildName(): void
    {
        $countryCityId = Uuid::randomHex();

        static::assertSame('country-district-route-' . $countryCityId, CountryDistrictRoute::buildName($countryCityId));
    }

    public function testLoad(): void
    {
        $countryCityId = Uuid::randomHex();
        $criteria = new Criteria();

        $districts = new CountryDistrictCollection([
            (new CountryDistrictEntity())->assign(['id' => Uuid::randomHex(), 'name' => '东城区']),
        ]);

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with(static::isInstanceOf(CountryDistrictCriteriaEvent::class))
            ->willReturnArgument(0);

        $countryDistrictRepository = $this->createMock(EntityRepository::class);
        $countryDistrictRepository->expects($this->once())
            ->method('search')
            ->willReturn(new EntitySearchResult(
                CountryDistrictDefinition::ENTITY_NAME,
                1,
                $districts,
                null,
                $criteria,
                $this->salesChannelContext->getContext(),
            ));

        $cacheTagCollector = $this->createMock(CacheTagCollector::class);
        $cacheTagCollector->expects($this->once())
            ->method('addTag')
            ->with(CountryDistrictRoute::buildName($countryCityId), CountryDistrictRoute::ALL_TAG);

        $route = new CountryDistrictRoute($countryDistrictRepository, $dispatcher, $cacheTagCollector, new ExtensionDispatcher(new EventDispatcher()));
        $response = $route->load($countryCityId, new Request(), $criteria, $this->salesChannelContext);

        static::assertSame($districts, $response->getDistricts());
    }

    public function testLoadFiltersByCityAndActiveState(): void
    {
        $countryCityId = Uuid::randomHex();
        $criteria = new Criteria();

        $countryDistrictRepository = $this->createMock(EntityRepository::class);
        $countryDistrictRepository->expects($this->once())
            ->method('search')
            ->with(
                static::callback(static fn (Criteria $criteria) => \count($criteria->getFilters()) === 2 && \count($criteria->getSorting()) === 2),
                $this->salesChannelContext->getContext(),
            )
            ->willReturn(new EntitySearchResult(
                CountryDistrictDefinition::ENTITY_NAME,
                0,
                new CountryDistrictCollection(),
                null,
                $criteria,
                $this->salesChannelContext->getContext(),
            ));

        $route = new CountryDistrictRoute(
            $countryDistrictRepository,
            static::createStub(EventDispatcherInterface::class),
            static::createStub(CacheTagCollector::class),
            new ExtensionDispatcher(new EventDispatcher()),
        );

        $route->load($countryCityId, new Request(), $criteria, $this->salesChannelContext);
    }

    public function testPublishesExtension(): void
    {
        $countryCityId = Uuid::randomHex();
        $request = new Request();
        $criteria = new Criteria();
        $response = static::createStub(CountryDistrictRouteResponse::class);

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('country-district-route.load.pre', function (CountryDistrictRouteExtension $extension) use ($countryCityId, $request, $criteria, $response): void {
            static::assertSame(['countryCityId' => $countryCityId, 'request' => $request, 'criteria' => $criteria, 'context' => $this->salesChannelContext], $extension->getParams());

            $extension->result = $response;
            $extension->stopPropagation();
        });

        $route = new CountryDistrictRoute(
            static::createStub(EntityRepository::class),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(CacheTagCollector::class),
            new ExtensionDispatcher($dispatcher),
        );

        static::assertSame($response, $route->load($countryCityId, $request, $criteria, $this->salesChannelContext));
    }
}
