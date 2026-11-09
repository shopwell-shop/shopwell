<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Category\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Category\CategoryCollection;
use Shopwell\Core\Content\Category\CategoryDefinition;
use Shopwell\Core\Content\Category\CategoryEntity;
use Shopwell\Core\Content\Category\CategoryException;
use Shopwell\Core\Content\Category\Extension\CategoryRouteExtension;
use Shopwell\Core\Content\Category\SalesChannel\CategoryRoute;
use Shopwell\Core\Content\Category\SalesChannel\CategoryRouteResponse;
use Shopwell\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(CategoryRoute::class)]
class CategoryRouteTest extends TestCase
{
    public function testLoadsCategoryData(): void
    {
        $category = $this->category();
        $context = Generator::generateSalesChannelContext();
        static::assertSame($category, $this->route($category, $context)->load($category->getId(), new Request(), $context)->getCategory());
    }

    public function testLoadsLinkCategory(): void
    {
        $category = $this->category(CategoryDefinition::TYPE_LINK);
        $context = Generator::generateSalesChannelContext();
        static::assertSame($category, $this->route($category, $context)->load($category->getId(), new Request(), $context)->getCategory());
    }

    public function testRejectsFolderOutsideHome(): void
    {
        $category = $this->category(CategoryDefinition::TYPE_FOLDER);
        $context = Generator::generateSalesChannelContext();
        $this->expectExceptionObject(CategoryException::categoryNotFound($category->getId()));
        $this->route($category, $context)->load($category->getId(), new Request(), $context);
    }

    public function testHomeResolvesNavigationIdAndCacheTag(): void
    {
        $category = $this->category(CategoryDefinition::TYPE_FOLDER);
        $context = Generator::generateSalesChannelContext();
        $context->getSalesChannel()->setNavigationCategoryId($category->getId());
        $collector = $this->createMock(CacheTagCollector::class);
        $collector->expects($this->once())->method('addTag')->with(CategoryRoute::buildName($category->getId()));
        $request = new Request();
        $response = $this->route($category, $context, $collector)->load(CategoryRoute::HOME, $request, $context);

        static::assertSame($category, $response->getCategory());
        static::assertSame($category->getId(), $request->attributes->get('navigationId'));
        static::assertSame(['navigationId' => $category->getId()], $request->attributes->get('_route_params'));
    }

    public function testRejectsMissingCategory(): void
    {
        $id = Uuid::randomHex();
        $context = Generator::generateSalesChannelContext();
        $this->expectExceptionObject(CategoryException::categoryNotFound($id));
        $this->route(null, $context)->load($id, new Request(), $context);
    }

    public function testPublishesExtension(): void
    {
        $id = Uuid::randomHex();
        $context = Generator::generateSalesChannelContext();
        $request = new Request();
        $response = new CategoryRouteResponse($this->category());
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(CategoryRouteExtension::NAME . '.pre', static function (CategoryRouteExtension $extension) use ($response): void {
            $extension->result = $response;
            $extension->stopPropagation();
        });
        $route = new CategoryRoute(static::createStub(SalesChannelRepository::class), static::createStub(CacheTagCollector::class), new ExtensionDispatcher($dispatcher));
        static::assertSame($response, $route->load($id, $request, $context));
    }

    private function category(string $type = CategoryDefinition::TYPE_PAGE): CategoryEntity
    {
        $category = new CategoryEntity();
        $category->setId(Uuid::randomHex());
        $category->setType($type);

        return $category;
    }

    private function route(?CategoryEntity $category, SalesChannelContext $context, ?CacheTagCollector $collector = null): CategoryRoute
    {
        $repository = static::createStub(SalesChannelRepository::class);
        $repository->method('search')->willReturn(new EntitySearchResult('category', $category === null ? 0 : 1, new CategoryCollection($category === null ? [] : [$category]), null, new Criteria(), $context->getContext()));

        return new CategoryRoute($repository, $collector ?? static::createStub(CacheTagCollector::class), new ExtensionDispatcher(new EventDispatcher()));
    }
}
