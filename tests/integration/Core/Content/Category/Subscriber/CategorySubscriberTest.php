<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Content\Category\Subscriber;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Category\SalesChannel\SalesChannelCategoryEntity;
use Shopwell\Core\Content\Test\Category\CategoryBuilder;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopwell\Core\Test\Stub\Framework\IdsCollection;
use Shopwell\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('discovery')]
class CategorySubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testSalesChannelCategoryLoadedAssignsSeoUrl(): void
    {
        $ids = new IdsCollection();

        $context = Context::createDefaultContext();

        static::getContainer()->get('category.repository')->create(
            [(new CategoryBuilder($ids, 'c.1'))->build()],
            $context
        );

        $criteria = new Criteria([$ids->get('c.1')]);

        $salesChannelContext = static::getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);

        $searchResult = static::getContainer()->get('sales_channel.category.repository')
            ->search($criteria, $salesChannelContext)->getEntities();

        $category = $searchResult->get($ids->get('c.1'));

        static::assertInstanceOf(SalesChannelCategoryEntity::class, $category);
        static::assertSame("124c71d524604ccbad6042edce3ac799/store-api/category/{$ids->get('c.1')}#", $category->getSeoUrl());
    }
}
