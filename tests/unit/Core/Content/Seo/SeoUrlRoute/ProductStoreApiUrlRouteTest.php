<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Seo\SeoUrlRoute;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Product\ProductDefinition;
use Shopwell\Core\Content\Seo\SeoUrlRoute\ProductStoreApiUrlRoute;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelEntity;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(ProductStoreApiUrlRoute::class)]
class ProductStoreApiUrlRouteTest extends TestCase
{
    public function testGetConfig(): void
    {
        $definition = new ProductDefinition();
        $config = (new ProductStoreApiUrlRoute($definition))->getConfig();

        static::assertSame($definition, $config->getDefinition());
        static::assertSame(ProductStoreApiUrlRoute::ROUTE_NAME, $config->getRouteName());
        static::assertSame('store-api.product.detail', $config->getRouteName());
        static::assertSame('', $config->getTemplate());
        static::assertTrue($config->getSkipInvalid());
        static::assertSame(['productId' => 'abc123'], $config->getPrimaryKeyParameter('abc123'));
    }

    public function testPrepareCriteriaScopesToSalesChannelVisibility(): void
    {
        $criteria = new Criteria();
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId('sales-channel-id');

        (new ProductStoreApiUrlRoute(new ProductDefinition()))->prepareCriteria($criteria, $salesChannel);

        static::assertEquals(
            [
                new EqualsFilter('active', true),
                new EqualsFilter('visibilities.salesChannelId', 'sales-channel-id'),
            ],
            $criteria->getFilters()
        );
        static::assertTrue($criteria->hasAssociation('options'));
    }
}
