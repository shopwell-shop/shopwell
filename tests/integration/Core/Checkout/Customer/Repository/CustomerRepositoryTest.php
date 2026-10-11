<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Checkout\Customer\Repository;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Customer\CustomerCollection;
use Shopwell\Core\Checkout\Customer\CustomerDefinition;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Term\EntityScoreQueryBuilder;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Term\SearchTermInterpreter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Framework\Util\Random;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Test\Stub\Framework\IdsCollection;
use Shopwell\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('checkout')]
class CustomerRepositoryTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    /**
     * @var EntityRepository<CustomerCollection>
     */
    private EntityRepository $repository;

    protected function setUp(): void
    {
        $this->repository = static::getContainer()->get('customer.repository');
        $this->connection = static::getContainer()->get(Connection::class);
    }

    public function testGetNoDuplicateMappingTableException(): void
    {
        $id = $this->createCustomer();

        $ids = new IdsCollection();

        $update = [
            'id' => $id,
            'tags' => [
                ['id' => $ids->get('tag-1'), 'name' => 'tag-1'],
                ['id' => $ids->get('tag-2'), 'name' => 'tag-2'],
                ['id' => $ids->get('tag-3'), 'name' => 'tag-3'],
            ],
        ];

        static::getContainer()->get('customer.repository')
            ->update([$update], Context::createDefaultContext());

        static::getContainer()->get('customer.repository')
            ->update([$update], Context::createDefaultContext());

        $count = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM customer_tag WHERE customer_id = :id', ['id' => Uuid::fromHexToBytes($id)]);

        static::assertSame(3, $count);
    }

    public function testSearchRanking(): void
    {
        $recordA = Uuid::randomHex();
        $recordB = Uuid::randomHex();
        $recordC = Uuid::randomHex();
        $recordD = Uuid::randomHex();

        $salutation = $this->getValidSalutationId();
        $address = [
            'name' => 'not not',
            'city' => 'not',
            'street' => 'not',
            'zipcode' => 'not',
            'salutationId' => $salutation,
            'country' => ['name' => 'not'],
        ];

        $matchTerm = Random::getAlphanumericString(20);

        // The address name is only reachable through an association and is therefore ranked lower
        // than the customer's own name field.
        $addressWithMatch = $address;
        $addressWithMatch['name'] = $matchTerm;

        $records = [
            [
                'id' => $recordA,
                'salesChannelId' => TestDefaults::SALES_CHANNEL,
                'defaultShippingAddress' => $addressWithMatch,
                'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
                'email' => Uuid::randomHex() . '@example.com',
                'password' => TestDefaults::HASHED_PASSWORD,
                'name' => 'not',
                'salutationId' => $salutation,
                'customerNumber' => 'not',
            ],
            [
                'id' => $recordB,
                'salesChannelId' => TestDefaults::SALES_CHANNEL,
                'defaultShippingAddress' => $address,
                'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
                'email' => Uuid::randomHex() . '@example.com',
                'password' => TestDefaults::HASHED_PASSWORD,
                'name' => $matchTerm,
                'salutationId' => $salutation,
                'customerNumber' => 'not',
            ],
            [
                'id' => $recordC,
                'salesChannelId' => TestDefaults::SALES_CHANNEL,
                'defaultShippingAddress' => $address,
                'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
                'email' => Uuid::randomHex() . '@example.com',
                'password' => TestDefaults::HASHED_PASSWORD,
                'name' => 'not',
                'salutationId' => $salutation,
                'customerNumber' => $matchTerm,
            ],
            [
                'id' => $recordD,
                'salesChannelId' => TestDefaults::SALES_CHANNEL,
                'defaultShippingAddress' => $address,
                'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
                'email' => $matchTerm . '@example.com',
                'password' => TestDefaults::HASHED_PASSWORD,
                'name' => 'not',
                'salutationId' => $salutation,
                'customerNumber' => 'not',
            ],
        ];

        $this->repository->create($records, Context::createDefaultContext());

        $context = Context::createDefaultContext();
        $criteria = new Criteria();

        $definition = static::getContainer()->get(CustomerDefinition::class);
        $builder = static::getContainer()->get(EntityScoreQueryBuilder::class);
        $pattern = static::getContainer()->get(SearchTermInterpreter::class)->interpret($matchTerm);
        $queries = $builder->buildScoreQueries($pattern, $definition, $definition->getEntityName(), $context);
        $criteria->addQuery(...$queries);

        $result = $this->repository->searchIds($criteria, $context);

        static::assertCount(4, $result->getIds());

        // the customer name is ranked higher than the address name
        static::assertGreaterThan(
            $result->getDataFieldOfId($recordA, '_score'),
            $result->getDataFieldOfId($recordB, '_score')
        );

        // the customer number is ranked higher than the email address
        static::assertGreaterThan(
            $result->getDataFieldOfId($recordD, '_score'),
            $result->getDataFieldOfId($recordC, '_score')
        );

        // the customer number is ranked higher than the address name
        static::assertGreaterThan(
            $result->getDataFieldOfId($recordA, '_score'),
            $result->getDataFieldOfId($recordC, '_score')
        );
    }

    public function testDeleteCustomerWithTags(): void
    {
        $customerId = Uuid::randomHex();
        $salutation = $this->getValidSalutationId();
        $customer = [
            'id' => $customerId,
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
            'defaultShippingAddress' => [
                'name' => 'not not',
                'city' => 'not',
                'street' => 'not',
                'zipcode' => 'not',
                'salutationId' => $salutation,
                'country' => ['name' => 'not'],
            ],
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'email' => 'test@example.com',
            'password' => TestDefaults::HASHED_PASSWORD,
            'name' => 'test',
            'salutationId' => $salutation,
            'customerNumber' => 'not',
            'tags' => [['name' => 'testTag']],
        ];

        $context = Context::createDefaultContext();
        $this->repository->create([$customer], $context);

        $this->repository->delete([['id' => $customerId]], $context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('id', $customerId));
        $result = $this->repository->searchIds($criteria, $context);

        static::assertCount(0, $result->getIds());
    }

    private function createCustomer(): string
    {
        $customerId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $customer = [
            'id' => $customerId,
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
            'defaultShippingAddress' => [
                'id' => $addressId,
                'name' => 'Max Mustermann',
                'street' => 'Musterstraße 1',
                'city' => 'Schöppingen',
                'zipcode' => '12345',
                'salutationId' => $this->getValidSalutationId(),
                'countryId' => $this->getValidCountryId(),
            ],
            'defaultBillingAddressId' => $addressId,
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'email' => 'foo@bar.de',
            'password' => TestDefaults::HASHED_PASSWORD,
            'name' => 'Max Mustermann',
            'salutationId' => $this->getValidSalutationId(),
            'customerNumber' => '12345',
        ];

        $repo = static::getContainer()->get('customer.repository');

        $repo->create([$customer], Context::createDefaultContext());

        return $customerId;
    }
}
