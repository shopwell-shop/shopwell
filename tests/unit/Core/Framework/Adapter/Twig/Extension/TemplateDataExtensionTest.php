<?php

declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Adapter\Twig\Extension;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\SalesChannelRequest;
use Shopwell\Core\Test\Generator;
use Shopwell\Core\Test\Stub\Doctrine\FakeConnection;
use Shopwell\Storefront\Controller\NavigationController;
use Shopwell\Storefront\Framework\Twig\NavigationInfo;
use Shopwell\Storefront\Framework\Twig\TemplateDataExtension;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(TemplateDataExtension::class)]
class TemplateDataExtensionTest extends TestCase
{
    public function testGetGlobalsWithoutRequest(): void
    {
        $globals = (new TemplateDataExtension(
            new RequestStack(),
            true,
            new FakeConnection([])
        ))->getGlobals();

        static::assertSame([], $globals);
    }

    public function testGetGlobalsWithoutSalesChannelContextInRequest(): void
    {
        $globals = (new TemplateDataExtension(
            new RequestStack([new Request()]),
            true,
            new FakeConnection([])
        ))->getGlobals();

        static::assertSame([], $globals);
    }

    public function testGetGlobals(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();
        $activeRoute = 'frontend.home.page';
        $controller = NavigationController::class;
        $themeId = Uuid::randomHex();
        $expectedMinSearchLength = 3;
        $navigationId = $salesChannelContext->getSalesChannel()->getNavigationCategoryId();

        $request = new Request(attributes: [
            PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT => $salesChannelContext,
            '_route' => $activeRoute,
            '_controller' => $controller . '::index',
            SalesChannelRequest::ATTRIBUTE_THEME_ID => $themeId,
        ]);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->exactly(2))
            ->method('fetchOne')
            ->willReturnCallback(static function (string $query) use ($expectedMinSearchLength, $navigationId) {
                if ($query === 'SELECT path FROM category WHERE id = :id') {
                    return $navigationId . '|019503b99fb57238a79d33ec1461e512|019503c1e1397116a3a7c754858927ef|';
                }
                if ($query === 'SELECT `min_search_length` FROM `product_search_config` WHERE `language_id` = :id') {
                    return $expectedMinSearchLength;
                }

                throw new \RuntimeException('Unexpected query: ' . $query);
            });

        $globals = (new TemplateDataExtension(
            new RequestStack([$request]),
            true,
            $connection,
        ))->getGlobals();

        static::assertArrayHasKey('shopwell', $globals);
        static::assertArrayHasKey('dateFormat', $globals['shopwell']);
        static::assertSame('Y-m-d\TH:i:sP', $globals['shopwell']['dateFormat']);
        static::assertArrayHasKey('navigation', $globals['shopwell']);
        $navigationInfo = $globals['shopwell']['navigation'];
        static::assertInstanceOf(NavigationInfo::class, $navigationInfo);
        static::assertSame($salesChannelContext->getSalesChannel()->getNavigationCategoryId(), $navigationInfo->id);
        // Make sure, the root category is not part of the pathIdList
        static::assertSame(['019503b99fb57238a79d33ec1461e512', '019503c1e1397116a3a7c754858927ef'], $navigationInfo->pathIdList);
        static::assertArrayHasKey('minSearchLength', $globals['shopwell']);
        static::assertSame($expectedMinSearchLength, $globals['shopwell']['minSearchLength']);
        static::assertArrayHasKey('showStagingBanner', $globals['shopwell']);
        static::assertTrue($globals['shopwell']['showStagingBanner']);

        static::assertArrayHasKey('themeId', $globals);
        static::assertSame($themeId, $globals['themeId']);

        static::assertArrayHasKey('controllerName', $globals);
        static::assertSame('Navigation', $globals['controllerName']);
        static::assertArrayHasKey('controllerAction', $globals);
        static::assertSame('index', $globals['controllerAction']);

        static::assertArrayHasKey('context', $globals);
        static::assertSame($salesChannelContext, $globals['context']);

        static::assertArrayHasKey('activeRoute', $globals);
        static::assertSame($activeRoute, $globals['activeRoute']);

        static::assertArrayHasKey('formViolations', $globals);
        static::assertNull($globals['formViolations']);
    }

    public function testLandingPageResolvesNavigationIdFromLinkedCategory(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();
        $rootCategoryId = $salesChannelContext->getSalesChannel()->getNavigationCategoryId();

        $landingPageId = Uuid::randomHex();
        $linkedCategoryId = Uuid::randomHex();

        $request = new Request(attributes: [
            PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT => $salesChannelContext,
            '_route' => 'frontend.landing.page',
            'landingPageId' => $landingPageId,
        ]);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->exactly(3))
            ->method('fetchOne')
            ->willReturnCallback(static function (string $query) use ($linkedCategoryId, $rootCategoryId) {
                if (str_contains($query, 'category_translation')) {
                    return $linkedCategoryId;
                }
                if (str_contains($query, 'SELECT path FROM category')) {
                    return $rootCategoryId . '|';
                }
                if (str_contains($query, 'min_search_length')) {
                    return 3;
                }

                throw new \RuntimeException('Unexpected query: ' . $query);
            });

        $globals = (new TemplateDataExtension(
            new RequestStack([$request]),
            false,
            $connection,
        ))->getGlobals();

        $navigationInfo = $globals['shopwell']['navigation'];
        static::assertInstanceOf(NavigationInfo::class, $navigationInfo);
        static::assertSame($linkedCategoryId, $navigationInfo->id);
    }

    public function testLandingPageFallsBackToRootCategoryWhenNoCategoryLinked(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();
        $rootCategoryId = $salesChannelContext->getSalesChannel()->getNavigationCategoryId();

        $landingPageId = Uuid::randomHex();

        $request = new Request(attributes: [
            PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT => $salesChannelContext,
            '_route' => 'frontend.landing.page',
            'landingPageId' => $landingPageId,
        ]);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->exactly(3))
            ->method('fetchOne')
            ->willReturnCallback(static function (string $query) {
                if (str_contains($query, 'category_translation')) {
                    return false;
                }
                if (str_contains($query, 'SELECT path FROM category')) {
                    return '';
                }
                if (str_contains($query, 'min_search_length')) {
                    return 3;
                }

                throw new \RuntimeException('Unexpected query: ' . $query);
            });

        $globals = (new TemplateDataExtension(
            new RequestStack([$request]),
            false,
            $connection,
        ))->getGlobals();

        $navigationInfo = $globals['shopwell']['navigation'];
        static::assertInstanceOf(NavigationInfo::class, $navigationInfo);
        static::assertSame($rootCategoryId, $navigationInfo->id);
    }
}
