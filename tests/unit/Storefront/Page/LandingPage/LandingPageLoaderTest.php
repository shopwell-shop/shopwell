<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Storefront\Page\LandingPage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockCollection;
use Shopwell\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockEntity;
use Shopwell\Core\Content\Cms\Aggregate\CmsSection\CmsSectionCollection;
use Shopwell\Core\Content\Cms\Aggregate\CmsSection\CmsSectionEntity;
use Shopwell\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotCollection;
use Shopwell\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopwell\Core\Content\Cms\CmsPageEntity;
use Shopwell\Core\Content\Cms\Exception\PageNotFoundException;
use Shopwell\Core\Content\LandingPage\LandingPageEntity;
use Shopwell\Core\Content\LandingPage\LandingPageException;
use Shopwell\Core\Content\LandingPage\SalesChannel\LandingPageRoute;
use Shopwell\Core\Content\LandingPage\SalesChannel\LandingPageRouteResponse;
use Shopwell\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Routing\RoutingException;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\System\SalesChannel\SalesChannelEntity;
use Shopwell\Core\Test\Generator;
use Shopwell\Storefront\Page\GenericPageLoader;
use Shopwell\Storefront\Page\LandingPage\LandingPageLoader;
use Shopwell\Storefront\Page\MetaInformation;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(LandingPageLoader::class)]
class LandingPageLoaderTest extends TestCase
{
    public function testNoLandingPageIdException(): void
    {
        $landingPageRouteMock = $this->createMock(LandingPageRoute::class);
        $landingPageRouteMock->expects($this->never())->method('load');

        $landingPageLoader = new LandingPageLoader(
            static::createStub(GenericPageLoader::class),
            $landingPageRouteMock,
            static::createStub(EventDispatcherInterface::class)
        );

        $request = new Request([], [], []);
        $salesChannelContext = $this->getSalesChannelContext();

        static::expectExceptionObject(RoutingException::missingRequestParameter('landingPageId', '/landingPageId'));
        $landingPageLoader->load($request, $salesChannelContext);
    }

    public function testNoLandingPageException(): void
    {
        $landingPageRouteMock = $this->createMock(LandingPageRoute::class);
        $landingPageRouteMock->expects($this->once())->method('load');

        $landingPageLoader = new LandingPageLoader(
            static::createStub(GenericPageLoader::class),
            $landingPageRouteMock,
            static::createStub(EventDispatcherInterface::class)
        );

        $landingPageId = Uuid::randomHex();
        $request = new Request([], [], ['landingPageId' => $landingPageId]);
        $salesChannelContext = $this->getSalesChannelContext();

        $expectedException = LandingPageException::notFound($landingPageId);

        // @deprecated tag:v6.8.0 - remove this if block
        if (!Feature::isActive('v6.8.0.0')) {
            $expectedException = new PageNotFoundException($landingPageId);
        }

        $this->expectExceptionObject($expectedException);
        $landingPageLoader->load($request, $salesChannelContext);
    }

    public function testItLoads(): void
    {
        $productId = Uuid::randomHex();
        $landingPageId = Uuid::randomHex();
        $request = new Request([], [], ['landingPageId' => $landingPageId]);
        $salesChannelContext = $this->getSalesChannelContext();

        $product = $this->getProduct($productId);
        $cmsPage = $this->getCmsPage($product);

        $landingPageLoader = $this->getLandingPageLoaderWithProduct($landingPageId, $cmsPage, $request, $salesChannelContext);

        $page = $landingPageLoader->load($request, $salesChannelContext);

        $cmsPageLoaded = $page->getLandingPage();
        static::assertNotNull($cmsPageLoaded);
        static::assertSame($cmsPage, $cmsPageLoaded->getCmsPage());
        static::assertSame($landingPageId, $page->getNavigationId());
    }

    public function testItLoadsProperPageMetaInformation(): void
    {
        $landingPageId = Uuid::randomHex();
        $request = new Request([], [], ['landingPageId' => $landingPageId]);
        $salesChannelContext = $this->getSalesChannelContext();

        $translated = [
            'name' => 'TEST_NAME',
            'metaTitle' => 'TEST_META_TITLE',
            'metaDescription' => 'TEST_META_DESCRIPTION',
            'keywords' => 'TEST_KEYWORDS',
        ];

        $expected = [
            'metaTitle' => $translated['metaTitle'],
            'metaDescription' => $translated['metaDescription'],
            'metaKeywords' => $translated['keywords'],
        ];

        $landingPageLoader = $this->getLandingPageLoaderWithTranslated($landingPageId, $translated, $request, $salesChannelContext);

        $page = $landingPageLoader->load($request, $salesChannelContext);
        $metaInformation = $page->getMetaInformation();

        static::assertInstanceOf(MetaInformation::class, $metaInformation);
        static::assertSame($metaInformation->getMetaTitle(), $expected['metaTitle']);
        static::assertSame($metaInformation->getMetaDescription(), $expected['metaDescription']);
        static::assertSame($metaInformation->getMetaKeywords(), $expected['metaKeywords']);
    }

    public function testItLoadsProperPageMetaInformationWithNameOnly(): void
    {
        $landingPageId = Uuid::randomHex();
        $request = new Request([], [], ['landingPageId' => $landingPageId]);
        $salesChannelContext = $this->getSalesChannelContext();

        $translated = [
            'name' => 'TEST_NAME',
        ];

        $expected = [
            'metaTitle' => $translated['name'],
            'metaDescription' => '',
            'metaKeywords' => '',
        ];

        $landingPageLoader = $this->getLandingPageLoaderWithTranslated($landingPageId, $translated, $request, $salesChannelContext);

        $page = $landingPageLoader->load($request, $salesChannelContext);
        $metaInformation = $page->getMetaInformation();

        static::assertInstanceOf(MetaInformation::class, $metaInformation);
        static::assertSame($metaInformation->getMetaTitle(), $expected['metaTitle']);
        static::assertSame($metaInformation->getMetaDescription(), $expected['metaDescription']);
        static::assertSame($metaInformation->getMetaKeywords(), $expected['metaKeywords']);
    }

    private function getLandingPageLoaderWithProduct(string $landingPageId, CmsPageEntity $cmsPage, Request $request, SalesChannelContext $salesChannelContext): LandingPageLoader
    {
        $landingPage = new LandingPageEntity();
        $landingPage->setId($landingPageId);
        $landingPage->setCmsPage($cmsPage);

        $landingPageRouteMock = static::createStub(LandingPageRoute::class);
        $landingPageRouteMock
            ->method('load')
            ->willReturn(new LandingPageRouteResponse($landingPage));

        return new LandingPageLoader(
            static::createStub(GenericPageLoader::class),
            $landingPageRouteMock,
            static::createStub(EventDispatcherInterface::class)
        );
    }

    /**
     * @param array<string> $translated
     */
    private function getLandingPageLoaderWithTranslated(string $landingPageId, array $translated, Request $request, SalesChannelContext $salesChannelContext): LandingPageLoader
    {
        $productId = Uuid::randomHex();
        $product = $this->getProduct($productId);
        $cmsPage = $this->getCmsPage($product);

        $landingPage = new LandingPageEntity();
        $landingPage->setId($landingPageId);
        $landingPage->setCmsPage($cmsPage);
        $landingPage->setTranslated($translated);
        $landingPage->setName('INCORRECT_NAME');

        $landingPageRouteMock = static::createStub(LandingPageRoute::class);
        $landingPageRouteMock
            ->method('load')
            ->willReturn(new LandingPageRouteResponse($landingPage));

        return new LandingPageLoader(
            static::createStub(GenericPageLoader::class),
            $landingPageRouteMock,
            static::createStub(EventDispatcherInterface::class)
        );
    }

    private function getProduct(string $productId): SalesChannelProductEntity
    {
        $product = new SalesChannelProductEntity();
        $product->setId($productId);

        return $product;
    }

    private function getSalesChannelContext(): SalesChannelContext
    {
        $salesChannelEntity = new SalesChannelEntity();
        $salesChannelEntity->setId('salesChannelId');

        return Generator::generateSalesChannelContext(
            salesChannel: $salesChannelEntity,
        );
    }

    private function getCmsPage(SalesChannelProductEntity $productEntity): CmsPageEntity
    {
        $cmsPageEntity = new CmsPageEntity();

        $cmsSectionEntity = new CmsSectionEntity();
        $cmsSectionEntity->setId(Uuid::randomHex());

        $cmsBlockEntity = new CmsBlockEntity();
        $cmsBlockEntity->setId(Uuid::randomHex());

        $cmsSlotEntity = new CmsSlotEntity();
        $cmsSlotEntity->setId(Uuid::randomHex());
        $cmsSlotEntity->setSlot(json_encode($productEntity->getTranslated(), \JSON_THROW_ON_ERROR));

        $cmsBlockEntity->setSlots(new CmsSlotCollection([$cmsSlotEntity]));
        $cmsSectionEntity->setBlocks(new CmsBlockCollection([$cmsBlockEntity]));
        $cmsPageEntity->setSections(new CmsSectionCollection([$cmsSectionEntity]));

        return $cmsPageEntity;
    }
}
