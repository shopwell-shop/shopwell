<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\System\Integration;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Api\Util\AccessKeyHelper;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Integration\IntegrationCollection;

/**
 * @internal
 */
#[Package('fundamentals@framework')]
class IntegrationRepositoryTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository<IntegrationCollection>
     */
    private EntityRepository $repository;

    protected function setUp(): void
    {
        $this->repository = static::getContainer()->get('integration.repository');
    }

    public function testCreationWithAccessKeys(): void
    {
        $id = Uuid::randomHex();

        $records = [
            [
                'id' => $id,
                'label' => 'My app',
                'accessKey' => AccessKeyHelper::generateAccessKey('integration'),
                'secretAccessKey' => AccessKeyHelper::generateSecretAccessKey(),
            ],
        ];

        $context = Context::createDefaultContext();

        $this->repository->create($records, $context);

        $entities = $this->repository->search(new Criteria([$id]), $context)->getEntities();
        $entity = $entities->first();

        static::assertNotNull($entity);
        static::assertCount(1, $entities);
        static::assertSame('My app', $entity->getLabel());
    }

    public function testCreationAdminDefaultsToFalse(): void
    {
        $id = Uuid::randomHex();

        $records = [
            [
                'id' => $id,
                'label' => 'My app',
                'accessKey' => AccessKeyHelper::generateAccessKey('integration'),
                'secretAccessKey' => AccessKeyHelper::generateSecretAccessKey(),
            ],
        ];

        $context = Context::createDefaultContext();

        $this->repository->create($records, $context);

        $entities = $this->repository->search(new Criteria([$id]), $context)->getEntities();
        $entity = $entities->first();

        static::assertNotNull($entity);
        static::assertCount(1, $entities);
        static::assertSame('My app', $entity->getLabel());
        static::assertFalse($entity->getAdmin());
    }

    public function testCreationWithAdminRole(): void
    {
        $id = Uuid::randomHex();

        $records = [
            [
                'id' => $id,
                'label' => 'My app',
                'accessKey' => AccessKeyHelper::generateAccessKey('integration'),
                'secretAccessKey' => AccessKeyHelper::generateSecretAccessKey(),
                'admin' => true,
            ],
        ];

        $context = Context::createDefaultContext();

        $this->repository->create($records, $context);

        $entities = $this->repository->search(new Criteria([$id]), $context)->getEntities();
        $entity = $entities->first();

        static::assertNotNull($entity);
        static::assertCount(1, $entities);
        static::assertSame('My app', $entity->getLabel());
        static::assertTrue($entity->getAdmin());
    }
}
