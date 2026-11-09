<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Storefront\Page\LandingPage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\LandingPage\LandingPageEntity;
use Shopwell\Core\Content\LandingPage\SalesChannel\LandingPageRoute;
use Shopwell\Core\Content\LandingPage\SalesChannel\LandingPageRouteResponse;
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

        $this->expectExceptionObject(RoutingException::missingRequestParameter('landingPageId', '/landingPageId'));
        $landingPageLoader->load($request, $salesChannelContext);
    }

    public function testItLoads(): void
    {
        $landingPageId = Uuid::randomHex();
        $request = new Request([], [], ['landingPageId' => $landingPageId]);
        $salesChannelContext = $this->getSalesChannelContext();

        $landingPageLoader = $this->getLandingPageLoaderWithProduct($landingPageId, $request, $salesChannelContext);

        $page = $landingPageLoader->load($request, $salesChannelContext);

        $loadedLandingPage = $page->getLandingPage();
        static::assertNotNull($loadedLandingPage);
        static::assertSame($landingPageId, $loadedLandingPage->getId());
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

        $metaInformation = $landingPageLoader->load($request, $salesChannelContext)->getMetaInformation();

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

        $metaInformation = $landingPageLoader->load($request, $salesChannelContext)->getMetaInformation();

        static::assertInstanceOf(MetaInformation::class, $metaInformation);
        static::assertSame($metaInformation->getMetaTitle(), $expected['metaTitle']);
        static::assertSame($metaInformation->getMetaDescription(), $expected['metaDescription']);
        static::assertSame($metaInformation->getMetaKeywords(), $expected['metaKeywords']);
    }

    private function getLandingPageLoaderWithProduct(
        string $landingPageId,
        Request $request,
        SalesChannelContext $salesChannelContext
    ): LandingPageLoader {
        $landingPage = new LandingPageEntity();
        $landingPage->setId($landingPageId);

        return new LandingPageLoader(
            static::createStub(GenericPageLoader::class),
            $this->createLandingPageRoute($landingPage, $request, $salesChannelContext),
            static::createStub(EventDispatcherInterface::class)
        );
    }

    /**
     * @param array<string> $translated
     */
    private function getLandingPageLoaderWithTranslated(
        string $landingPageId,
        array $translated,
        Request $request,
        SalesChannelContext $salesChannelContext
    ): LandingPageLoader {
        $landingPage = new LandingPageEntity();
        $landingPage->setId($landingPageId);
        $landingPage->setTranslated($translated);
        $landingPage->setName('INCORRECT_NAME');

        return new LandingPageLoader(
            static::createStub(GenericPageLoader::class),
            $this->createLandingPageRoute($landingPage, $request, $salesChannelContext),
            static::createStub(EventDispatcherInterface::class)
        );
    }

    private function createLandingPageRoute(
        LandingPageEntity $landingPage,
        Request $expectedRequest,
        SalesChannelContext $expectedContext
    ): LandingPageRoute {
        $landingPageRoute = $this->createMock(LandingPageRoute::class);
        $landingPageRoute
            ->expects($this->once())
            ->method('load')
            ->willReturnCallback(static function (
                string $landingPageId,
                Request $request,
                SalesChannelContext $context
            ) use (
                $landingPage,
                $expectedRequest,
                $expectedContext
            ): LandingPageRouteResponse {
                static::assertSame($landingPage->getId(), $landingPageId);
                static::assertSame($expectedRequest, $request);
                static::assertSame($expectedContext, $context);

                return new LandingPageRouteResponse($landingPage);
            });

        return $landingPageRoute;
    }

    private function getSalesChannelContext(): SalesChannelContext
    {
        $salesChannelEntity = new SalesChannelEntity();
        $salesChannelEntity->setId('salesChannelId');

        return Generator::generateSalesChannelContext(
            salesChannel: $salesChannelEntity,
        );
    }
}
