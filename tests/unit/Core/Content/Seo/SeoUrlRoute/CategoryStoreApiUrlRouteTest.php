<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Seo\SeoUrlRoute;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Category\CategoryDefinition;
use Shopwell\Core\Content\Seo\SeoUrlRoute\CategoryStoreApiUrlRoute;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelEntity;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(CategoryStoreApiUrlRoute::class)]
class CategoryStoreApiUrlRouteTest extends TestCase
{
    public function testGetConfig(): void
    {
        $definition = new CategoryDefinition();
        $config = (new CategoryStoreApiUrlRoute($definition))->getConfig();

        static::assertSame($definition, $config->getDefinition());
        static::assertSame(CategoryStoreApiUrlRoute::ROUTE_NAME, $config->getRouteName());
        static::assertSame('store-api.category.detail', $config->getRouteName());
        static::assertSame('', $config->getTemplate());
        static::assertTrue($config->getSkipInvalid());
        static::assertSame(['navigationId' => 'abc123'], $config->getPrimaryKeyParameter('abc123'));
    }

    public function testPrepareCriteriaScopesToTheSalesChannelCategoryTrees(): void
    {
        $criteria = new Criteria();
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId('sales-channel-id');
        $salesChannel->setNavigationCategoryId('navigation-id');
        $salesChannel->setServiceCategoryId('service-id');
        // footer category id intentionally left unset (null) and must be skipped

        (new CategoryStoreApiUrlRoute(new CategoryDefinition()))->prepareCriteria($criteria, $salesChannel);

        $filters = $criteria->getFilters();
        static::assertCount(3, $filters);

        static::assertEquals(new EqualsFilter('active', true), $filters[0]);
        static::assertInstanceOf(NotFilter::class, $filters[1]);

        static::assertInstanceOf(MultiFilter::class, $filters[2]);
        static::assertSame(MultiFilter::CONNECTION_OR, $filters[2]->getOperator());
        static::assertEquals(
            [
                new ContainsFilter('path', '|navigation-id|'),
                new ContainsFilter('path', '|service-id|'),
            ],
            $filters[2]->getQueries()
        );
    }
}
