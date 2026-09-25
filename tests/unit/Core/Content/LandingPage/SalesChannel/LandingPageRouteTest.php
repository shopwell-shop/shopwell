<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\LandingPage\SalesChannel;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Cms\CmsPageCollection;
use Shopwell\Core\Content\Cms\CmsPageEntity;
use Shopwell\Core\Content\Cms\SalesChannel\SalesChannelCmsPageLoaderInterface;
use Shopwell\Core\Content\Cms\Service\EntityCmsSlotConfigInheritanceBuilder;
use Shopwell\Core\Content\LandingPage\LandingPageCollection;
use Shopwell\Core\Content\LandingPage\LandingPageDefinition;
use Shopwell\Core\Content\LandingPage\LandingPageEntity;
use Shopwell\Core\Content\LandingPage\LandingPageException;
use Shopwell\Core\Content\LandingPage\SalesChannel\LandingPageRoute;
use Shopwell\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticSalesChannelRepository;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(LandingPageRoute::class)]
class LandingPageRouteTest extends TestCase
{
    private MockObject&SalesChannelCmsPageLoaderInterface $cmsPageLoader;

    private SalesChannelContext $salesChannelContext;

    protected function setUp(): void
    {
        $this->cmsPageLoader = $this->createMock(SalesChannelCmsPageLoaderInterface::class);

        $salesChannelContext = static::createStub(SalesChannelContext::class);
        $salesChannelContext->method('getSalesChannelId')->willReturn(Uuid::randomHex());
        $salesChannelContext->method('getLanguageId')->willReturn(Uuid::randomHex());
        $salesChannelContext->method('getContext')->willReturn(Context::createDefaultContext());
        $this->salesChannelContext = $salesChannelContext;
    }

    #[TestDox('The resolved cms page is loaded onto the landing page')]
    public function testLoadsLandingPageWithCmsPage(): void
    {
        $landingPage = $this->createLandingPage(withCmsPage: true);
        $cmsPage = new CmsPageEntity();
        $cmsPage->setId($landingPage->getCmsPageId() ?? '');

        $this->cmsPageLoader
            ->expects($this->once())
            ->method('load')
            ->willReturn($this->createCmsPageResult(new CmsPageCollection([$cmsPage])));

        $response = $this->createRoute($landingPage)->load($landingPage->getId(), new Request(), $this->salesChannelContext);

        static::assertSame($landingPage, $response->getLandingPage());
        static::assertSame($cmsPage, $response->getLandingPage()->getCmsPage());
    }

    #[TestDox('A landing page without an assigned cms page is returned without loading one')]
    public function testLoadsLandingPageWithoutCmsPageId(): void
    {
        $landingPage = $this->createLandingPage(withCmsPage: false);

        $this->cmsPageLoader->expects($this->never())->method('load');

        $response = $this->createRoute($landingPage)->load($landingPage->getId(), new Request(), $this->salesChannelContext);

        static::assertSame($landingPage, $response->getLandingPage());
        static::assertNull($response->getLandingPage()->getCmsPage());
    }

    #[TestDox('An unresolvable cms page fails the route')]
    public function testThrowsWhenCmsPageCannotBeLoaded(): void
    {
        $landingPage = $this->createLandingPage(withCmsPage: true);

        $this->cmsPageLoader
            ->expects($this->once())
            ->method('load')
            ->willReturn($this->createCmsPageResult(new CmsPageCollection()));

        $this->expectExceptionObject(LandingPageException::notFound($landingPage->getCmsPageId() ?? ''));

        $this->createRoute($landingPage)->load($landingPage->getId(), new Request(), $this->salesChannelContext);
    }

    #[TestDox('An unknown landing page id fails the route')]
    public function testThrowsWhenLandingPageIsNotFound(): void
    {
        $missingId = Uuid::randomHex();

        $this->cmsPageLoader->expects($this->never())->method('load');

        /** @var StaticSalesChannelRepository<LandingPageCollection> $repository */
        $repository = new StaticSalesChannelRepository([new LandingPageCollection()]);

        $route = new LandingPageRoute(
            $repository,
            $this->cmsPageLoader,
            new EntityCmsSlotConfigInheritanceBuilder(static::createStub(Connection::class)),
            static::createStub(LandingPageDefinition::class),
            static::createStub(CacheTagCollector::class)
        );

        $this->expectExceptionObject(LandingPageException::notFound($missingId));

        $route->load($missingId, new Request(), $this->salesChannelContext);
    }

    private function createRoute(LandingPageEntity $landingPage): LandingPageRoute
    {
        /** @var StaticSalesChannelRepository<LandingPageCollection> $repository */
        $repository = new StaticSalesChannelRepository([new LandingPageCollection([$landingPage])]);

        return new LandingPageRoute(
            $repository,
            $this->cmsPageLoader,
            new EntityCmsSlotConfigInheritanceBuilder(static::createStub(Connection::class)),
            static::createStub(LandingPageDefinition::class),
            static::createStub(CacheTagCollector::class)
        );
    }

    /**
     * @return EntitySearchResult<CmsPageCollection>
     */
    private function createCmsPageResult(CmsPageCollection $pages): EntitySearchResult
    {
        return new EntitySearchResult(
            'cms_page',
            $pages->count(),
            $pages,
            null,
            new Criteria(),
            Context::createDefaultContext()
        );
    }

    private function createLandingPage(bool $withCmsPage): LandingPageEntity
    {
        $landingPage = new LandingPageEntity();
        $landingPage->setId(Uuid::randomHex());
        $landingPage->setUniqueIdentifier($landingPage->getId());
        if ($withCmsPage) {
            $landingPage->setCmsPageId(Uuid::randomHex());
        }

        return $landingPage;
    }
}
