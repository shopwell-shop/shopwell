<?php declare(strict_types=1);

namespace Shopwell\Core\Test\Integration\Traits;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Before;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopwell\Core\Framework\Uuid\Uuid;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @internal
 */
trait EntityFixturesBase
{
    private Context $entityFixtureContext;

    #[Before]
    public function initializeFixtureContext(): void
    {
        $this->entityFixtureContext = Context::createDefaultContext();
    }

    public function setFixtureContext(Context $context): void
    {
        $this->entityFixtureContext = $context;
    }

    /**
     * @return EntityRepository<covariant EntityCollection<covariant Entity>>
     */
    public static function getFixtureRepository(string $fixtureName): EntityRepository
    {
        $container = KernelLifecycleManager::getKernel()->getContainer();

        if ($container->has('test.service_container')) {
            /** @var ContainerInterface $container */
            $container = $container->get('test.service_container');
        }

        return $container->get(DefinitionInstanceRegistry::class)->getRepository($fixtureName);
    }

    /**
     * @param array<string, array<string, mixed>> $fixtureData
     * @param EntityRepository<covariant EntityCollection<covariant Entity>> $repository
     */
    public function createFixture(string $fixtureName, array $fixtureData, EntityRepository $repository): Entity
    {
        self::ensureATransactionIsActive();

        $repository->create([$fixtureData[$fixtureName]], $this->entityFixtureContext);

        if (\array_key_exists('mediaType', $fixtureData[$fixtureName])) {
            $connection = KernelLifecycleManager::getKernel()
                ->getContainer()
                ->get(Connection::class);
            $connection->update(
                'media',
                [
                    'media_type' => serialize($fixtureData[$fixtureName]['mediaType']),
                ],
                ['id' => Uuid::fromHexToBytes($fixtureData[$fixtureName]['id'])]
            );
        }

        $criteria = new Criteria([$fixtureData[$fixtureName]['id']]);

        $entity = $repository
            ->search($criteria, $this->entityFixtureContext)
            ->getEntities()
            ->get($fixtureData[$fixtureName]['id']);

        static::assertInstanceOf(Entity::class, $entity);

        return $entity;
    }

    private static function ensureATransactionIsActive(): void
    {
        $connection = KernelLifecycleManager::getKernel()
            ->getContainer()
            ->get(Connection::class);

        if (!$connection->isTransactionActive()) {
            throw new \BadMethodCallException('You should not start writing to the database outside of transactions');
        }
    }
}
