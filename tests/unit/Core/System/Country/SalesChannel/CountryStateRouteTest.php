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
use Shopwell\Core\System\Country\Aggregate\CountryState\CountryStateCollection;
use Shopwell\Core\System\Country\Event\CountryStateCriteriaEvent;
use Shopwell\Core\System\Country\Extension\CountryStateRouteExtension;
use Shopwell\Core\System\Country\SalesChannel\CountryStateRoute;
use Shopwell\Core\System\Country\SalesChannel\CountryStateRouteResponse;
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
#[CoversClass(CountryStateRoute::class)]
class CountryStateRouteTest extends TestCase
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

    public function testLoad(): void
    {
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher
            ->expects($this->exactly(1))
            ->method('dispatch')
            ->with(static::isInstanceOf(CountryStateCriteriaEvent::class));

        $countryStateRepository = $this->createMock(EntityRepository::class);
        $countryStateRepository->expects($this->once())
            ->method('search')
            ->willReturn(new EntitySearchResult(
                'country_state',
                0,
                new CountryStateCollection(),
                null,
                new Criteria(),
                $this->salesChannelContext->getContext(),
            ));

        $cacheTagCollector = static::createStub(CacheTagCollector::class);

        $route = new CountryStateRoute($countryStateRepository, $dispatcher, $cacheTagCollector, new ExtensionDispatcher(new EventDispatcher()));
        $route->load(Uuid::randomHex(), new Request(), new Criteria(), $this->salesChannelContext);
    }

    public function testPublishesExtension(): void
    {
        $countryId = Uuid::randomHex();
        $request = new Request();
        $criteria = new Criteria();
        $response = static::createStub(CountryStateRouteResponse::class);

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('country-state-route.load.pre', function (CountryStateRouteExtension $extension) use ($countryId, $request, $criteria, $response): void {
            static::assertSame(['countryId' => $countryId, 'request' => $request, 'criteria' => $criteria, 'context' => $this->salesChannelContext], $extension->getParams());

            $extension->result = $response;
            $extension->stopPropagation();
        });

        $route = new CountryStateRoute(
            static::createStub(EntityRepository::class),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(CacheTagCollector::class),
            new ExtensionDispatcher($dispatcher),
        );

        static::assertSame($response, $route->load($countryId, $request, $criteria, $this->salesChannelContext));
    }
}
