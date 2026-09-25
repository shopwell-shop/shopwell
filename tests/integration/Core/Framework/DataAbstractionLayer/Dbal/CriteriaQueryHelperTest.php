<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\DataAbstractionLayer\Dbal;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Product\ProductDefinition;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopwell\Core\Framework\DataAbstractionLayer\Dbal\CriteriaQueryBuilder;
use Shopwell\Core\Framework\DataAbstractionLayer\Dbal\Exception\InvalidSortingDirectionException;
use Shopwell\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Query\ScoreQuery;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\System\Tax\TaxCollection;

/**
 * @internal
 */
#[Package('framework')]
class CriteriaQueryHelperTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testInvalidSortingDirection(): void
    {
        $context = Context::createDefaultContext();
        /** @var EntityRepository<TaxCollection> $taxRepository */
        $taxRepository = static::getContainer()->get('tax.repository');

        $criteria = new Criteria();

        $criteria->addSorting(new FieldSorting('rate', 'invalid direction'));

        if (Feature::isActive('v6.8.0.0')) {
            static::expectExceptionObject(DataAbstractionLayerException::invalidSortingDirection('invalid direction'));
        } else {
            static::expectException(InvalidSortingDirectionException::class);
        }
        $taxRepository->search($criteria, $context);
    }

    public function testDoNotSortByScoreAutomaticallyIfNoScoreQueryOrSearchTermIsSet(): void
    {
        $productDefinition = static::getContainer()->get(ProductDefinition::class);
        $queryMock = $this->createMock(QueryBuilder::class);
        $queryMock
            ->expects($this->never())
            ->method('addOrderBy');

        $builder = static::getContainer()->get(CriteriaQueryBuilder::class);
        $builder->build($queryMock, $productDefinition, new Criteria(), Context::createDefaultContext());
    }

    public function testDoNotSortByScoreManuallyIfNoScoreQueryOrSearchTermIsSet(): void
    {
        $criteria = new Criteria();
        $criteria->addSorting(new FieldSorting('_score'));
        $productDefinition = static::getContainer()->get(ProductDefinition::class);
        $queryMock = $this->createMock(QueryBuilder::class);
        $queryMock
            ->expects($this->never())
            ->method('addOrderBy');

        $builder = static::getContainer()->get(CriteriaQueryBuilder::class);
        $builder->build($queryMock, $productDefinition, $criteria, Context::createDefaultContext());
    }

    public function testSortByScoreIfScoreQueryIsSet(): void
    {
        $productDefinition = static::getContainer()->get(ProductDefinition::class);
        $criteria = new Criteria();
        $criteria->addQuery(new ScoreQuery(new ContainsFilter('name', 'test matching'), 1000));
        $queryMock = $this->createMock(QueryBuilder::class);
        $queryMock
            ->expects($this->once())
            ->method('addOrderBy')
            ->with('_score', 'DESC');

        $builder = static::getContainer()->get(CriteriaQueryBuilder::class);
        $builder->build($queryMock, $productDefinition, $criteria, Context::createDefaultContext());
    }

    public function testSortByScoreIfSearchTermIsSet(): void
    {
        $productDefinition = static::getContainer()->get(ProductDefinition::class);
        $criteria = new Criteria();
        $criteria->setTerm('searchTerm');
        $queryMock = $this->createMock(QueryBuilder::class);
        $queryMock
            ->expects($this->once())
            ->method('addOrderBy')
            ->with('_score', 'DESC');

        $builder = static::getContainer()->get(CriteriaQueryBuilder::class);
        $builder->build($queryMock, $productDefinition, $criteria, Context::createDefaultContext());
    }

    public function testSortByScoreAndAdditionalSorting(): void
    {
        $productDefinition = static::getContainer()->get(ProductDefinition::class);
        $criteria = new Criteria();
        $criteria->setTerm('searchTerm');
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::ASCENDING));

        $queryBuilder = new QueryBuilder(static::createStub(Connection::class));

        $builder = static::getContainer()->get(CriteriaQueryBuilder::class);
        $builder->build($queryBuilder, $productDefinition, $criteria, Context::createDefaultContext());

        static::assertSame($queryBuilder->getOrderByParts(), [
            'MIN(`product`.`created_at`) ASC',
            '_score DESC',
        ]);
    }

    public function testSortByScoreAndAdditionalSortingWithScore(): void
    {
        $productDefinition = static::getContainer()->get(ProductDefinition::class);
        $criteria = new Criteria();
        $criteria->setTerm('searchTerm');
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::ASCENDING));
        $criteria->addSorting(new FieldSorting('_score', FieldSorting::ASCENDING));
        $queryBuilder = new QueryBuilder(static::createStub(Connection::class));

        $builder = static::getContainer()->get(CriteriaQueryBuilder::class);
        $builder->build($queryBuilder, $productDefinition, $criteria, Context::createDefaultContext());

        static::assertSame($queryBuilder->getOrderByParts(), [
            'MIN(`product`.`created_at`) ASC',
            '_score ASC',
        ]);
    }
}
