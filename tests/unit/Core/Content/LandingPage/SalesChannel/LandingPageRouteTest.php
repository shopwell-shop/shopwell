<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\LandingPage\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\LandingPage\Extension\LandingPageRouteExtension;
use Shopwell\Core\Content\LandingPage\LandingPageCollection;
use Shopwell\Core\Content\LandingPage\LandingPageEntity;
use Shopwell\Core\Content\LandingPage\LandingPageException;
use Shopwell\Core\Content\LandingPage\SalesChannel\LandingPageRoute;
use Shopwell\Core\Content\LandingPage\SalesChannel\LandingPageRouteResponse;
use Shopwell\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopwell\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(LandingPageRoute::class)]
class LandingPageRouteTest extends TestCase
{
    public function testLoadsLandingPageDataAndCollectsCacheTag(): void
    {
        $context = Generator::generateSalesChannelContext();
        $page = new LandingPageEntity();
        $page->setId(Uuid::randomHex());
        $repository = static::createStub(SalesChannelRepository::class);
        $repository->method('search')->willReturn(new EntitySearchResult('landing_page', 1, new LandingPageCollection([$page]), null, new Criteria(), $context->getContext()));
        $collector = $this->createMock(CacheTagCollector::class);
        $collector->expects($this->once())->method('addTag')->with(LandingPageRoute::buildName($page->getId()));
        $route = new LandingPageRoute($repository, $collector, new ExtensionDispatcher(new EventDispatcher()));

        static::assertSame($page, $route->load($page->getId(), new Request(), $context)->getLandingPage());
    }

    public function testRejectsMissingLandingPage(): void
    {
        $context = Generator::generateSalesChannelContext();
        $id = Uuid::randomHex();
        $repository = static::createStub(SalesChannelRepository::class);
        $repository->method('search')->willReturn(new EntitySearchResult('landing_page', 0, new LandingPageCollection(), null, new Criteria(), $context->getContext()));
        $route = new LandingPageRoute($repository, static::createStub(CacheTagCollector::class), new ExtensionDispatcher(new EventDispatcher()));
        $this->expectExceptionObject(LandingPageException::notFound($id));
        $route->load($id, new Request(), $context);
    }

    public function testPublishesExtension(): void
    {
        $context = Generator::generateSalesChannelContext();
        $page = new LandingPageEntity();
        $response = new LandingPageRouteResponse($page);
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(LandingPageRouteExtension::NAME . '.pre', static function (LandingPageRouteExtension $extension) use ($response): void {
            $extension->result = $response;
            $extension->stopPropagation();
        });
        $route = new LandingPageRoute(static::createStub(SalesChannelRepository::class), static::createStub(CacheTagCollector::class), new ExtensionDispatcher($dispatcher));

        static::assertSame($response, $route->load(Uuid::randomHex(), new Request(), $context));
    }
}
