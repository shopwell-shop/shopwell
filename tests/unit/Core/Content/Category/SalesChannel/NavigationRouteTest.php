<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Category\SalesChannel;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Category\CategoryCollection;
use Shopwell\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopwell\Core\Content\Category\SalesChannel\NavigationRoute;
use Shopwell\Core\Content\Category\Service\DefaultCategoryLevelLoader;
use Shopwell\Core\Content\Category\Tree\CategoryTreePathResolver;
use Shopwell\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\Test\Annotation\DisabledFeatures;
use Shopwell\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(NavigationRoute::class)]
class NavigationRouteTest extends TestCase
{
    private NavigationRoute $navigationRoute;

    private Connection&MockObject $connection;

    /**
     * @var SalesChannelRepository<CategoryCollection>&Stub
     */
    private SalesChannelRepository&Stub $categoryRepository;

    private CacheTagCollector&Stub $cacheTagCollector;

    private CategoryTreePathResolver&Stub $categoryTreePathResolver;

    private DefaultCategoryLevelLoader&Stub $defaultCategoryLevelLoader;

    private SalesChannelContext $salesChannelContext;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->categoryRepository = static::createStub(SalesChannelRepository::class);
        $this->cacheTagCollector = static::createStub(CacheTagCollector::class);
        $this->categoryTreePathResolver = static::createStub(CategoryTreePathResolver::class);
        $this->defaultCategoryLevelLoader = static::createStub(DefaultCategoryLevelLoader::class);

        $this->navigationRoute = $this->createRoute();

        $this->salesChannelContext = Generator::generateSalesChannelContext();
    }

    public function testLoadAddsCacheTagsCorrectly(): void
    {
        $activeId = Uuid::randomHex();
        $rootId = Generator::NAVIGATION_CATEGORY;
        $request = new Request();
        $criteria = new Criteria();

        $this->connection
            ->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn([
                [
                    'LOWER(HEX(`id`))' => $activeId,
                    'path' => '|' . $rootId . '|' . $activeId . '|',
                    'level' => 2,
                ],
                [
                    'LOWER(HEX(`id`))' => $rootId,
                    'path' => '|' . $rootId . '|',
                    'level' => 1,
                ],
            ]);

        $categoryRepository = $this->createMock(SalesChannelRepository::class);
        $categoryRepository
            ->expects($this->never())
            ->method('search');

        $categoryTreePathResolver = $this->createMock(CategoryTreePathResolver::class);
        $categoryTreePathResolver
            ->expects($this->once())
            ->method('getAdditionalPathsToLoad')
            ->willReturn([]);

        $cacheTagCollector = $this->createMock(CacheTagCollector::class);
        $cacheTagCollector
            ->expects($this->once())
            ->method('addTag')
            ->with(NavigationRoute::ALL_TAG);

        $this->createRoute($categoryRepository, $cacheTagCollector, $categoryTreePathResolver)->load(
            $activeId,
            $rootId,
            $request,
            $this->salesChannelContext,
            $criteria
        );
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testLoadAddsDeprecatedCacheTagsCorrectly(): void
    {
        $activeId = Uuid::randomHex();
        $rootId = Generator::NAVIGATION_CATEGORY;
        $request = new Request();
        $criteria = new Criteria();

        $this->connection
            ->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn([
                [
                    'LOWER(HEX(`id`))' => $activeId,
                    'path' => '|' . $rootId . '|' . $activeId . '|',
                    'level' => 2,
                ],
                [
                    'LOWER(HEX(`id`))' => $rootId,
                    'path' => '|' . $rootId . '|',
                    'level' => 1,
                ],
            ]);

        $categoryRepository = $this->createMock(SalesChannelRepository::class);
        $categoryRepository
            ->expects($this->never())
            ->method('search');

        $categoryTreePathResolver = $this->createMock(CategoryTreePathResolver::class);
        $categoryTreePathResolver
            ->expects($this->once())
            ->method('getAdditionalPathsToLoad')
            ->willReturn([]);

        $cacheTagCollector = $this->createMock(CacheTagCollector::class);
        $cacheTagCollector
            ->expects($this->once())
            ->method('addTag')
            ->with(
                NavigationRoute::ALL_TAG,
                NavigationRoute::buildName($this->salesChannelContext->getSalesChannelId()),
                NavigationRoute::buildName($activeId)
            );

        $this->createRoute($categoryRepository, $cacheTagCollector, $categoryTreePathResolver)->load(
            $activeId,
            $rootId,
            $request,
            $this->salesChannelContext,
            $criteria
        );
    }

    public function testLoadWithInvalidCategoryThrowsException(): void
    {
        $activeId = Uuid::randomHex();
        $rootId = Uuid::randomHex();
        $request = new Request();
        $criteria = new Criteria();

        $this->connection
            ->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn([]);

        $this->expectExceptionObject(new CategoryNotFoundException($activeId));

        $this->navigationRoute->load(
            $activeId,
            $rootId,
            $request,
            $this->salesChannelContext,
            $criteria
        );
    }

    /**
     * @param (SalesChannelRepository<CategoryCollection>&MockObject)|null $categoryRepository
     */
    private function createRoute(
        ?SalesChannelRepository $categoryRepository = null,
        ?CacheTagCollector $cacheTagCollector = null,
        ?CategoryTreePathResolver $categoryTreePathResolver = null,
    ): NavigationRoute {
        return new NavigationRoute(
            $this->connection,
            $categoryRepository ?? $this->categoryRepository,
            $cacheTagCollector ?? $this->cacheTagCollector,
            $categoryTreePathResolver ?? $this->categoryTreePathResolver,
            $this->defaultCategoryLevelLoader,
        );
    }
}
