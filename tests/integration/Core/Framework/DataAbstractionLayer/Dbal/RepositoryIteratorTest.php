<?php

declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\DataAbstractionLayer\Dbal;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Product\ProductCollection;
use Shopwell\Core\Content\Test\Product\ProductBuilder;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Dbal\Common\RepositoryIterator;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\System\SystemConfig\SystemConfigCollection;
use Shopwell\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
#[Package('framework')]
class RepositoryIteratorTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testIteratedSearch(): void
    {
        $context = Context::createDefaultContext();
        /** @var EntityRepository<SystemConfigCollection> $systemConfigRepository */
        $systemConfigRepository = static::getContainer()->get('system_config.repository');

        $criteria = new Criteria();
        $criteria->addFilter(new ContainsFilter('configurationKey', 'core'));
        $criteria->setLimit(1);

        /** @var RepositoryIterator<SystemConfigCollection> $iterator */
        $iterator = new RepositoryIterator($systemConfigRepository, $context, $criteria);

        $offset = 1;
        while (($result = $iterator->fetch()) !== null) {
            static::assertNotNull($result->getEntities()->first()?->getId());
            static::assertEquals(
                [new ContainsFilter('configurationKey', 'core')],
                $criteria->getFilters()
            );
            static::assertCount(0, $criteria->getPostFilters());
            static::assertSame($offset, $criteria->getOffset());
            ++$offset;
        }
    }

    public function testFetchIdsIsNotRunningInfinitely(): void
    {
        $context = Context::createDefaultContext();
        /** @var EntityRepository<SystemConfigCollection> $systemConfigRepository */
        $systemConfigRepository = static::getContainer()->get('system_config.repository');

        $iterator = new RepositoryIterator($systemConfigRepository, $context, new Criteria());

        $iteration = 0;
        while ($iterator->fetchIds() !== null && $iteration < 100) {
            ++$iteration;
        }

        static::assertTrue($iteration < 100);
    }

    public function testFetchIdAutoIncrement(): void
    {
        /** @var EntityRepository<ProductCollection> $productRepository */
        $productRepository = static::getContainer()->get('product.repository');

        $context = Context::createDefaultContext();

        $ids = new IdsCollection();

        $builder = new ProductBuilder($ids, 'product1');
        $builder->price(1);
        $productRepository->create([$builder->build()], $context);

        $builder = new ProductBuilder($ids, 'product2');
        $builder->price(2);
        $productRepository->create([$builder->build()], $context);

        $builder = new ProductBuilder($ids, 'product3');
        $builder->price(3);
        $productRepository->create([$builder->build()], $context);

        $criteria = new Criteria([$ids->get('product1'), $ids->get('product2'), $ids->get('product3')]);
        $criteria->setLimit(1);
        $iterator = new RepositoryIterator($productRepository, $context, $criteria);

        $totalFetchedIds = 0;
        while ($iterator->fetchIds()) {
            ++$totalFetchedIds;
        }
        static::assertSame($totalFetchedIds, 3);
    }

    public function testFetchAutoIncrementDoesNotSkipBatches(): void
    {
        /** @var EntityRepository<ProductCollection> $productRepository */
        $productRepository = static::getContainer()->get('product.repository');

        $context = Context::createDefaultContext();
        $ids = new IdsCollection();

        foreach (['product1', 'product2', 'product3'] as $productNumber) {
            $builder = new ProductBuilder($ids, $productNumber);
            $builder->price(1);
            $productRepository->create([$builder->build()], $context);
        }

        $criteria = new Criteria(array_values($ids->getList(['product1', 'product2', 'product3'])));
        $criteria->setLimit(1);
        $iterator = new RepositoryIterator($productRepository, $context, $criteria);

        $fetchedIds = [];
        while (($result = $iterator->fetch()) !== null) {
            $fetchedIds[] = $result->getEntities()->first()?->getId();
        }

        static::assertSame(
            [$ids->get('product1'), $ids->get('product2'), $ids->get('product3')],
            $fetchedIds
        );
    }

    public function testFetchWithSortingUsesOffsetPagination(): void
    {
        /** @var EntityRepository<ProductCollection> $productRepository */
        $productRepository = static::getContainer()->get('product.repository');

        $context = Context::createDefaultContext();
        $ids = new IdsCollection();

        foreach (['product1', 'product2', 'product3'] as $productNumber) {
            $builder = new ProductBuilder($ids, $productNumber);
            $builder->price(1);
            $productRepository->create([$builder->build()], $context);
        }

        $criteria = new Criteria(array_values($ids->getList(['product1', 'product2', 'product3'])));
        $criteria->addSorting(new FieldSorting('productNumber', FieldSorting::DESCENDING));
        $criteria->setLimit(1);
        $iterator = new RepositoryIterator($productRepository, $context, $criteria);

        $fetchedIds = [];
        while (($result = $iterator->fetch()) !== null) {
            $fetchedIds[] = $result->getEntities()->first()?->getId();
        }

        static::assertSame(
            [$ids->get('product3'), $ids->get('product2'), $ids->get('product1')],
            $fetchedIds
        );
    }
}
