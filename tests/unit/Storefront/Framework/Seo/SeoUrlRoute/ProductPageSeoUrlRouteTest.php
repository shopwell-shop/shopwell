<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Storefront\Framework\Seo\SeoUrlRoute;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Product\ProductDefinition;
use Shopwell\Core\Content\Product\ProductEntity;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\ArrayEntity;
use Shopwell\Core\System\SalesChannel\SalesChannelEntity;
use Shopwell\Storefront\Framework\Seo\SeoUrlRoute\ProductPageSeoUrlRoute;
use Shopwell\Storefront\Framework\StorefrontFrameworkException;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductPageSeoUrlRoute::class)]
class ProductPageSeoUrlRouteTest extends TestCase
{
    public function testGetConfig(): void
    {
        $productDefinition = static::createStub(ProductDefinition::class);
        $route = new ProductPageSeoUrlRoute($productDefinition);

        $config = $route->getConfig();
        static::assertSame($productDefinition, $config->getDefinition());
        static::assertSame(ProductPageSeoUrlRoute::ROUTE_NAME, $config->getRouteName());
        static::assertSame(ProductPageSeoUrlRoute::DEFAULT_TEMPLATE, $config->getTemplate());
        static::assertTrue($config->getSkipInvalid());
    }

    public function testCriteria(): void
    {
        $route = new ProductPageSeoUrlRoute(static::createStub(ProductDefinition::class));

        $criteria = new Criteria();
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId('test');
        $route->prepareCriteria($criteria, $salesChannel);
        static::assertTrue($criteria->hasEqualsFilter('active'));

        static::assertTrue($criteria->hasEqualsFilter('visibilities.salesChannelId'));
    }

    public function testMappingWithInvalidEntity(): void
    {
        $route = new ProductPageSeoUrlRoute(static::createStub(ProductDefinition::class));

        $this->expectExceptionObject(StorefrontFrameworkException::invalidArgument('SEO URL Mapping expects argument to be a ProductEntity'));
        $route->getMapping(new ArrayEntity(), new SalesChannelEntity());
    }

    public function testMapping(): void
    {
        $route = new ProductPageSeoUrlRoute(static::createStub(ProductDefinition::class));

        $product = new ProductEntity();
        $product->setId('test');
        $data = $route->getMapping($product, new SalesChannelEntity());

        static::assertNull($data->getError());
        static::assertSame($product, $data->getEntity());
        static::assertSame(['productId' => 'test'], $data->getInfoPathContext());

        $context = $data->getSeoPathInfoContext();
        static::assertArrayHasKey('product', $context);
        static::assertSame($product->jsonSerialize(), $context['product']);
    }
}
