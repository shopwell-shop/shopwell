<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\System\Country\Repository;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Term\EntityScoreQueryBuilder;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Term\SearchTermInterpreter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Country\Aggregate\CountryState\CountryStateCollection;

/**
 * @internal
 */
#[Package('fundamentals@discovery')]
class CountryStateRepositoryTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    /**
     * @var EntityRepository<CountryStateCollection>
     */
    private EntityRepository $repository;

    protected function setUp(): void
    {
        $this->repository = static::getContainer()->get('country_state.repository');
    }

    public function testSearchRanking(): void
    {
        $country = Uuid::randomHex();

        static::getContainer()->get('country.repository')->create([
            ['id' => $country, 'name' => 'test'],
        ], Context::createDefaultContext());

        $recordA = Uuid::randomHex();
        $recordB = Uuid::randomHex();

        $records = [
            ['id' => $recordA, 'name' => 'match', 'shortCode' => 'test',    'countryId' => $country],
            ['id' => $recordB, 'name' => 'not',   'shortCode' => 'match 1', 'countryId' => $country],
        ];

        $this->repository->create($records, Context::createDefaultContext());

        $criteria = new Criteria();

        $builder = static::getContainer()->get(EntityScoreQueryBuilder::class);
        $pattern = static::getContainer()->get(SearchTermInterpreter::class)->interpret('match');
        $context = Context::createDefaultContext();
        $queries = $builder->buildScoreQueries(
            $pattern,
            $this->repository->getDefinition(),
            $this->repository->getDefinition()->getEntityName(),
            $context
        );
        $criteria->addQuery(...$queries);

        $result = $this->repository->searchIds($criteria, Context::createDefaultContext());

        static::assertCount(2, $result->getIds());

        static::assertSame(
            [$recordA, $recordB],
            $result->getIds()
        );

        static::assertGreaterThan(
            $result->getDataFieldOfId($recordB, '_score'),
            $result->getDataFieldOfId($recordA, '_score')
        );
    }
}
