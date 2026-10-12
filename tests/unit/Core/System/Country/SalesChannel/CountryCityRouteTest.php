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
use Shopwell\Core\System\Country\Aggregate\CountryCity\CountryCityCollection;
use Shopwell\Core\System\Country\Aggregate\CountryCity\CountryCityDefinition;
use Shopwell\Core\System\Country\Aggregate\CountryCity\CountryCityEntity;
use Shopwell\Core\System\Country\Event\CountryCityCriteriaEvent;
use Shopwell\Core\System\Country\Extension\CountryCityRouteExtension;
use Shopwell\Core\System\Country\SalesChannel\CountryCityRoute;
use Shopwell\Core\System\Country\SalesChannel\CountryCityRouteResponse;
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
#[CoversClass(CountryCityRoute::class)]
class CountryCityRouteTest extends TestCase
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
        $countryStateId = Uuid::randomHex();

        static::assertSame('country-city-route-' . $countryStateId, CountryCityRoute::buildName($countryStateId));
    }

    public function testLoad(): void
    {
        $countryStateId = Uuid::randomHex();
        $criteria = new Criteria();

        $cities = new CountryCityCollection([
            (new CountryCityEntity())->assign(['id' => Uuid::randomHex(), 'name' => '市辖区']),
        ]);

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with(static::isInstanceOf(CountryCityCriteriaEvent::class))
            ->willReturnArgument(0);

        $countryCityRepository = $this->createMock(EntityRepository::class);
        $countryCityRepository->expects($this->once())
            ->method('search')
            ->willReturn(new EntitySearchResult(
                CountryCityDefinition::ENTITY_NAME,
                1,
                $cities,
                null,
                $criteria,
                $this->salesChannelContext->getContext(),
            ));

        $cacheTagCollector = $this->createMock(CacheTagCollector::class);
        $cacheTagCollector->expects($this->once())
            ->method('addTag')
            ->with(CountryCityRoute::buildName($countryStateId), CountryCityRoute::ALL_TAG);

        $route = new CountryCityRoute($countryCityRepository, $dispatcher, $cacheTagCollector, new ExtensionDispatcher(new EventDispatcher()));
        $response = $route->load($countryStateId, new Request(), $criteria, $this->salesChannelContext);

        static::assertSame($cities, $response->getCities());
    }

    public function testLoadFiltersByStateAndActiveState(): void
    {
        $countryStateId = Uuid::randomHex();
        $criteria = new Criteria();

        $countryCityRepository = $this->createMock(EntityRepository::class);
        $countryCityRepository->expects($this->once())
            ->method('search')
            ->with(
                static::callback(static fn (Criteria $criteria) => \count($criteria->getFilters()) === 2 && \count($criteria->getSorting()) === 2),
                $this->salesChannelContext->getContext(),
            )
            ->willReturn(new EntitySearchResult(
                CountryCityDefinition::ENTITY_NAME,
                0,
                new CountryCityCollection(),
                null,
                $criteria,
                $this->salesChannelContext->getContext(),
            ));

        $route = new CountryCityRoute(
            $countryCityRepository,
            static::createStub(EventDispatcherInterface::class),
            static::createStub(CacheTagCollector::class),
            new ExtensionDispatcher(new EventDispatcher()),
        );

        $route->load($countryStateId, new Request(), $criteria, $this->salesChannelContext);
    }

    public function testPublishesExtension(): void
    {
        $countryStateId = Uuid::randomHex();
        $request = new Request();
        $criteria = new Criteria();
        $response = static::createStub(CountryCityRouteResponse::class);

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('country-city-route.load.pre', function (CountryCityRouteExtension $extension) use ($countryStateId, $request, $criteria, $response): void {
            static::assertSame(['countryStateId' => $countryStateId, 'request' => $request, 'criteria' => $criteria, 'context' => $this->salesChannelContext], $extension->getParams());

            $extension->result = $response;
            $extension->stopPropagation();
        });

        $route = new CountryCityRoute(
            static::createStub(EntityRepository::class),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(CacheTagCollector::class),
            new ExtensionDispatcher($dispatcher),
        );

        static::assertSame($response, $route->load($countryStateId, $request, $criteria, $this->salesChannelContext));
    }
}
