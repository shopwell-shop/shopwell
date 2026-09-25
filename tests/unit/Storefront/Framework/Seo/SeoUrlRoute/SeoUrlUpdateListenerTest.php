<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Storefront\Framework\Seo\SeoUrlRoute;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Category\Event\CategoryIndexerEvent;
use Shopwell\Core\Content\LandingPage\Event\LandingPageIndexerEvent;
use Shopwell\Core\Content\Product\Events\ProductIndexerEvent;
use Shopwell\Core\Content\Seo\SeoUrlUpdater;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Storefront\Framework\Seo\SeoUrlRoute\LandingPageSeoUrlRoute;
use Shopwell\Storefront\Framework\Seo\SeoUrlRoute\NavigationPageSeoUrlRoute;
use Shopwell\Storefront\Framework\Seo\SeoUrlRoute\ProductPageSeoUrlRoute;
use Shopwell\Storefront\Framework\Seo\SeoUrlRoute\SeoUrlUpdateListener;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(SeoUrlUpdateListener::class)]
class SeoUrlUpdateListenerTest extends TestCase
{
    private SeoUrlUpdater&MockObject $seoUrlUpdater;

    private SeoUrlUpdateListener $listener;

    protected function setUp(): void
    {
        $this->seoUrlUpdater = $this->createMock(SeoUrlUpdater::class);
        $this->listener = new SeoUrlUpdateListener($this->seoUrlUpdater);
    }

    public function testUpdateCategoryUrls(): void
    {
        $childUuid = Uuid::randomHex();
        $parentUuid = Uuid::randomHex();

        $event = new CategoryIndexerEvent([$parentUuid, $childUuid], Context::createDefaultContext());

        $this->seoUrlUpdater->expects($this->once())
            ->method('update')
            ->with(
                NavigationPageSeoUrlRoute::ROUTE_NAME,
                [$parentUuid, $childUuid]
            );

        $this->listener->updateCategoryUrls($event);
    }

    public function testUpdateCategoryUrlsSkipped(): void
    {
        $childUuid = Uuid::randomHex();
        $parentUuid = Uuid::randomHex();

        $event = new CategoryIndexerEvent([$parentUuid, $childUuid], Context::createDefaultContext(), [SeoUrlUpdateListener::CATEGORY_SEO_URL_UPDATER]);

        $this->seoUrlUpdater->expects($this->never())
            ->method('update');

        $this->listener->updateCategoryUrls($event);
    }

    public function testUpdateProductUrls(): void
    {
        $childUuid = Uuid::randomHex();
        $parentUuid = Uuid::randomHex();

        $event = new ProductIndexerEvent([$parentUuid, $childUuid], Context::createDefaultContext());

        $this->seoUrlUpdater->expects($this->once())
            ->method('update')
            ->with(
                ProductPageSeoUrlRoute::ROUTE_NAME,
                [$parentUuid, $childUuid]
            );

        $this->listener->updateProductUrls($event);
    }

    public function testUpdateProductUrlsSkips(): void
    {
        $childUuid = Uuid::randomHex();
        $parentUuid = Uuid::randomHex();

        $event = new ProductIndexerEvent([$parentUuid, $childUuid], Context::createDefaultContext(), [SeoUrlUpdateListener::PRODUCT_SEO_URL_UPDATER]);

        $this->seoUrlUpdater->expects($this->never())
            ->method('update');

        $this->listener->updateProductUrls($event);
    }

    public function testUpdateLandingPageUrls(): void
    {
        $childUuid = Uuid::randomHex();
        $parentUuid = Uuid::randomHex();

        $event = new LandingPageIndexerEvent([$parentUuid, $childUuid], Context::createDefaultContext());

        $this->seoUrlUpdater->expects($this->once())
            ->method('update')
            ->with(
                LandingPageSeoUrlRoute::ROUTE_NAME,
                [$parentUuid, $childUuid]
            );

        $this->listener->updateLandingPageUrls($event);
    }

    public function testUpdateLandingPageUrlsSkips(): void
    {
        $childUuid = Uuid::randomHex();
        $parentUuid = Uuid::randomHex();

        $event = new LandingPageIndexerEvent([$parentUuid, $childUuid], Context::createDefaultContext(), [SeoUrlUpdateListener::LANDING_PAGE_SEO_URL_UPDATER]);

        $this->seoUrlUpdater->expects($this->never())
            ->method('update');

        $this->listener->updateLandingPageUrls($event);
    }
}
