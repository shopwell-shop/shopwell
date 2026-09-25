<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\System\CustomEntity;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\App\AppEntity;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Framework\Util\Database\TableHelper;
use Shopwell\Core\Framework\Util\Filesystem;
use Shopwell\Core\System\CustomEntity\CustomEntityCollection;
use Shopwell\Core\System\CustomEntity\CustomEntityLifecycleService;
use Shopwell\Core\System\CustomEntity\Schema\CustomEntityPersister;
use Shopwell\Core\System\CustomEntity\Schema\CustomEntitySchemaUpdater;
use Shopwell\Core\System\CustomEntity\Xml\Config\CustomEntityEnrichmentService;
use Shopwell\Core\System\CustomEntity\Xml\CustomEntityXmlSchemaValidator;
use Shopwell\Core\Test\Stub\App\StaticSourceResolver;
use Shopwell\Tests\Integration\Core\Framework\App\AppFixture;
use Symfony\Component\Clock\NativeClock;

/**
 * @internal
 */
#[Package('framework')]
class CustomEntityLifecycleServiceTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Context $context;

    private Connection $connection;

    private AppFixture $appFixture;

    /**
     * @var EntityRepository<CustomEntityCollection>
     */
    private EntityRepository $customEntityRepository;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
        $this->connection = static::getContainer()->get(Connection::class);
        $this->customEntityRepository = static::getContainer()->get('custom_entity.repository');

        $appFixture = static::getContainer()->get(AppFixture::class);
        \assert($appFixture instanceof AppFixture);
        $this->appFixture = $appFixture;
    }

    public function testRemoveAppSoftDeletesCustomEntitiesWhenKeepingUserData(): void
    {
        $this->stopTransactionAfter();

        $manifest = $this->appFixture->loadManifest(__DIR__ . '/_fixtures/CustomEntityLifecycleServiceTest/default/app/manifest.xml');
        $app = $this->appFixture->createApp($manifest);

        $this->createLifecycleService($app)->updateApp($app);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('appId', $app->getId()));

        $customEntities = $this->customEntityRepository->search($criteria, $this->context)->getEntities();

        static::assertTrue(TableHelper::tableExists($this->connection, 'custom_entity_test'));
        static::assertCount(1, $customEntities);

        $customEntity = $customEntities->first();
        static::assertNotNull($customEntity);

        $this->createLifecycleService($app)->removeApp($app, $this->context, true);

        $customEntities = $this->customEntityRepository->search(new Criteria([$customEntity->getId()]), $this->context)->getEntities();

        $customEntity = $customEntities->first();
        static::assertNotNull($customEntity);

        static::assertTrue(TableHelper::tableExists($this->connection, 'custom_entity_test'));
        static::assertCount(1, $customEntities);
        static::assertNotNull($customEntity->getDeletedAt());

        $this->connection->executeStatement('DELETE FROM custom_entity');
        $this->connection->executeStatement('DELETE FROM app WHERE name ="customEntities"');
        $this->connection->executeStatement('DELETE FROM integration WHERE label ="customEntities"');
        $this->connection->executeStatement('DELETE FROM acl_role WHERE name ="customEntities"');
        $this->connection->executeStatement('DROP TABLE `custom_entity_test`');

        $this->startTransactionBefore();
    }

    public function testRemoveAppHardDeletesCustomEntities(): void
    {
        $this->stopTransactionAfter();

        $manifest = $this->appFixture->loadManifest(__DIR__ . '/_fixtures/CustomEntityLifecycleServiceTest/default/app/manifest.xml');
        $app = $this->appFixture->createApp($manifest);

        $this->createLifecycleService($app)->updateApp($app);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('appId', $app->getId()));

        $customEntities = $this->customEntityRepository->search($criteria, $this->context)->getEntities();

        static::assertTrue(TableHelper::tableExists($this->connection, 'custom_entity_test'));
        static::assertCount(1, $customEntities);

        $customEntity = $customEntities->first();
        static::assertNotNull($customEntity);

        $this->createLifecycleService($app)->removeApp($app, $this->context, false);

        $customEntities = $this->customEntityRepository->search(new Criteria([$customEntity->getId()]), $this->context)->getEntities();

        static::assertFalse(TableHelper::tableExists($this->connection, 'custom_entity_test'));
        static::assertCount(0, $customEntities);

        $this->connection->executeStatement('DELETE FROM custom_entity');
        $this->connection->executeStatement('DELETE FROM app WHERE name ="customEntities"');
        $this->connection->executeStatement('DELETE FROM integration WHERE label ="customEntities"');
        $this->connection->executeStatement('DELETE FROM acl_role WHERE name ="customEntities"');

        $this->startTransactionBefore();
    }

    private function createLifecycleService(AppEntity $app): CustomEntityLifecycleService
    {
        return new CustomEntityLifecycleService(
            static::getContainer()->get(CustomEntityPersister::class),
            static::getContainer()->get(CustomEntitySchemaUpdater::class),
            static::getContainer()->get(CustomEntityEnrichmentService::class),
            static::getContainer()->get(CustomEntityXmlSchemaValidator::class),
            new StaticSourceResolver([
                $app->getName() => new Filesystem(__DIR__ . '/_fixtures/CustomEntityLifecycleServiceTest/default/app'),
            ]),
            $this->connection,
            $this->customEntityRepository,
            new NativeClock(),
        );
    }
}
