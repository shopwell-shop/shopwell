<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Storefront\Page;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Product\Exception\ProductNotFoundException;
use Shopwell\Core\Content\Product\ProductException;
use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\SystemConfig\SystemConfigService;
use Shopwell\Storefront\Page\Product\ProductPageLoadedEvent;
use Shopwell\Storefront\Page\Product\ProductPageLoader;
use Shopwell\Storefront\Test\Page\StorefrontPageTestBehaviour;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
class ProductPageTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontPageTestBehaviour;

    public function testItRequiresProductParam(): void
    {
        $request = new Request();
        $context = $this->createSalesChannelContextWithNavigation();

        $this->expectParamMissingException('productId');
        $this->getPageLoader()->load($request, $context);
    }

    public function testItRequiresAValidProductParam(): void
    {
        $request = new Request([], [], ['productId' => '99999911ffff4fffafffffff19830531']);
        $context = $this->createSalesChannelContextWithNavigation();

        if (!Feature::isActive('v6.8.0.0')) {
            $this->expectException(ProductNotFoundException::class);
        } else {
            $this->expectException(ProductException::class);
        }

        $this->getPageLoader()->load($request, $context);
    }

    public function testItFailsWithANonExistingProduct(): void
    {
        $context = $this->createSalesChannelContextWithNavigation();
        $request = new Request([], [], ['productId' => Uuid::randomHex()]);

        $event = null;
        $this->catchEvent(ProductPageLoadedEvent::class, $event);

        if (!Feature::isActive('v6.8.0.0')) {
            $this->expectException(ProductNotFoundException::class);
        } else {
            $this->expectException(ProductException::class);
        }

        $this->getPageLoader()->load($request, $context);
    }

    public function testItDoesLoadATestProduct(): void
    {
        $context = $this->createSalesChannelContextWithNavigation();
        $product = $this->getRandomProduct($context);

        $request = new Request([], [], ['productId' => $product->getId()]);

        $event = null;
        $this->catchEvent(ProductPageLoadedEvent::class, $event);

        $page = $this->getPageLoader()->load($request, $context);

        static::assertSame(StorefrontPageTestConstants::PRODUCT_NAME, $page->getProduct()->getName());
        self::assertPageEvent(ProductPageLoadedEvent::class, $event, $context, $request, $page);
    }

    public function testItDispatchPageCriteriaEvent(): void
    {
        $context = $this->createSalesChannelContextWithNavigation();
        $product = $this->getRandomProduct($context);

        $request = new Request([], [], ['productId' => $product->getId()]);

        $page = $this->getPageLoader()->load($request, $context);

        static::assertSame($page->getProduct()->getId(), $product->getId());
    }

    public function testItDoesLoadACloseProductWithHideCloseEnabled(): void
    {
        $context = $this->createSalesChannelContextWithNavigation();

        // enable hideCloseoutProductsWhenOutOfStock filter
        static::getContainer()->get(SystemConfigService::class)
            ->set('core.listing.hideCloseoutProductsWhenOutOfStock', true);

        $product = $this->getRandomProduct($context, 1, true);

        $request = new Request([], [], ['productId' => $product->getId()]);

        $event = null;
        $this->catchEvent(ProductPageLoadedEvent::class, $event);

        $page = $this->getPageLoader()->load($request, $context);

        static::assertSame(StorefrontPageTestConstants::PRODUCT_NAME, $page->getProduct()->getName());
        self::assertPageEvent(ProductPageLoadedEvent::class, $event, $context, $request, $page);
    }

    public function testItDoesFailWithACloseProductWithHideCloseEnabledWhenOutOfStock(): void
    {
        $context = $this->createSalesChannelContextWithNavigation();

        // enable hideCloseoutProductsWhenOutOfStock filter
        static::getContainer()->get(SystemConfigService::class)
            ->set('core.listing.hideCloseoutProductsWhenOutOfStock', true);

        $product = $this->getRandomProduct($context, 0, true);

        $request = new Request([], [], ['productId' => $product->getId()]);

        $event = null;
        $this->catchEvent(ProductPageLoadedEvent::class, $event);

        if (!Feature::isActive('v6.8.0.0')) {
            $this->expectException(ProductNotFoundException::class);
        } else {
            $this->expectException(ProductException::class);
        }

        $this->getPageLoader()->load($request, $context);
    }

    protected function getPageLoader(): ProductPageLoader
    {
        return static::getContainer()->get(ProductPageLoader::class);
    }
}
