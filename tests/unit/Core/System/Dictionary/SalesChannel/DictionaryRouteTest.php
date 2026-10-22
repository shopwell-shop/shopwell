<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Dictionary\SalesChannel;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopwell\Core\System\Dictionary\Aggregate\DictionaryEntry\DictionaryEntryCollection;
use Shopwell\Core\System\Dictionary\DictionaryException;
use Shopwell\Core\System\Dictionary\SalesChannel\DictionaryRoute;
use Shopwell\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * The route is public and takes the dictionary type from the path, so two things decide its
 * behaviour: an unknown technical name must be a 404 and not an empty list, and the query must be
 * narrowed to the resolved type and to active entries before the caller's criteria is searched.
 *
 * @internal
 */
#[Package('fundamentals@discovery')]
#[CoversClass(DictionaryRoute::class)]
class DictionaryRouteTest extends TestCase
{
    public function testTheRouteIsNamedAndRefusesToBeDecorated(): void
    {
        $route = $this->route(static::createStub(Connection::class), static::createStub(SalesChannelRepository::class));

        static::assertSame('dictionary-route', DictionaryRoute::buildName());

        $this->expectException(DecorationPatternException::class);
        $route->getDecorated();
    }

    public function testTheResolvedTypeAndTheActiveFlagAreAddedToTheCriteria(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchOne')
            ->willReturn('type-id');

        $repository = $this->createMock(SalesChannelRepository::class);
        $repository->expects($this->once())
            ->method('search')
            ->willReturnCallback(static fn (Criteria $criteria, SalesChannelContext $context): EntitySearchResult => new EntitySearchResult(
                'dictionary_entry',
                0,
                new DictionaryEntryCollection(),
                null,
                $criteria,
                $context->getContext(),
            ));

        $cacheTagCollector = $this->createMock(CacheTagCollector::class);
        $cacheTagCollector->expects($this->once())->method('addTag')->with('dictionary-route');

        $context = Generator::generateSalesChannelContext();
        $criteria = new Criteria();
        $request = new Request(attributes: ['technicalName' => 'salutation']);

        $response = $this->route($connection, $repository, $cacheTagCollector)->load($request, $context, $criteria);

        static::assertCount(0, $response->getEntries());

        $filters = $criteria->getFilters();

        static::assertInstanceOf(EqualsFilter::class, $filters[0]);
        static::assertSame('dictionaryTypeId', $filters[0]->getField());
        static::assertSame('type-id', $filters[0]->getValue());

        static::assertInstanceOf(EqualsFilter::class, $filters[1]);
        static::assertSame('active', $filters[1]->getField());
        static::assertTrue($filters[1]->getValue());

        static::assertInstanceOf(FieldSorting::class, $criteria->getSorting()[0]);
        static::assertSame('position', $criteria->getSorting()[0]->getField());
        static::assertSame(FieldSorting::ASCENDING, $criteria->getSorting()[0]->getDirection());
    }

    public function testAnUnknownTechnicalNameIsNotFoundInsteadOfAnEmptyList(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchOne')->willReturn(false);

        $repository = $this->createMock(SalesChannelRepository::class);
        $repository->expects($this->never())->method('search');

        $route = $this->route($connection, $repository);

        $this->expectExceptionObject(DictionaryException::dictionaryTypeNotFound('unknown'));

        $route->load(
            new Request(attributes: ['technicalName' => 'unknown']),
            Generator::generateSalesChannelContext(),
            new Criteria(),
        );
    }

    /**
     * @param SalesChannelRepository<DictionaryEntryCollection> $repository
     */
    private function route(
        Connection $connection,
        SalesChannelRepository $repository,
        ?CacheTagCollector $cacheTagCollector = null,
    ): DictionaryRoute {
        return new DictionaryRoute(
            $repository,
            $connection,
            $cacheTagCollector ?? $this->createMock(CacheTagCollector::class),
            new ExtensionDispatcher($this->createMock(EventDispatcherInterface::class)),
        );
    }
}
