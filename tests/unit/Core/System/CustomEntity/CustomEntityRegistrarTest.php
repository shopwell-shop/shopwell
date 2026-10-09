<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\CustomEntity;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEventFactory;
use Shopwell\Core\Framework\DataAbstractionLayer\Read\EntityReaderInterface;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntityAggregatorInterface;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Shopwell\Core\Framework\DataAbstractionLayer\VersionManager;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\CustomEntity\CustomEntityRegistrar;
use Shopwell\Core\System\CustomEntity\Schema\DynamicEntityDefinition;
use Shopwell\Core\Test\Stub\Doctrine\TestExceptionFactory;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CustomEntityRegistrar::class)]
class CustomEntityRegistrarTest extends TestCase
{
    public function testSkipsRegistrationIfFetchingCustomEntitiesFailWithException(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchAllAssociative')
            ->willThrowException(TestExceptionFactory::createException('test'));

        $definitionInstanceRegistry = $this->createMock(DefinitionInstanceRegistry::class);
        $definitionInstanceRegistry->expects($this->never())
            ->method('register');

        $container = new Container();
        $container->set(Connection::class, $connection);
        $container->set(DefinitionInstanceRegistry::class, $definitionInstanceRegistry);

        $registrar = new CustomEntityRegistrar($container);

        $registrar->register();

        static::assertCount(3, $container->getServiceIds());
    }

    public function testFetchesCustomEntitiesFromDbAndRegistersThemAtTheContainer(): void
    {
        $container = new Container();

        /** @var DynamicEntityDefinition[] $definitions */
        $definitions = [
            DynamicEntityDefinition::create('ce_test_one', [], [], $container),
            DynamicEntityDefinition::create('ce_test_two', [], [], $container),
        ];

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn([
                [
                    'name' => 'ce_test_one',
                    'fields' => json_encode([]),
                    'flags' => json_encode([]),
                ],
                [
                    'name' => 'ce_test_two',
                    'fields' => json_encode([]),
                    'flags' => json_encode([]),
                ],
            ]);

        $container->set(Connection::class, $connection);
        $container->set(DefinitionInstanceRegistry::class, new DefinitionInstanceRegistry($container, [], []));
        $container->set(EntityReaderInterface::class, static::createStub(EntityReaderInterface::class));
        $container->set(VersionManager::class, static::createStub(VersionManager::class));
        $container->set(EntitySearcherInterface::class, static::createStub(EntitySearcherInterface::class));
        $container->set(EntityAggregatorInterface::class, static::createStub(EntityAggregatorInterface::class));
        $container->set('event_dispatcher', static::createStub(EventDispatcherInterface::class));
        $container->set(EntityLoadedEventFactory::class, static::createStub(EntityLoadedEventFactory::class));

        $registrar = new CustomEntityRegistrar($container);

        $registrar->register();

        static::assertInstanceOf(DynamicEntityDefinition::class, $definitions[0]);
        static::assertSame('ce_test_one', $definitions[0]->getEntityName());
        static::assertInstanceOf(EntityRepository::class, $container->get($definitions[0]->getEntityName() . '.repository'));

        static::assertInstanceOf(DynamicEntityDefinition::class, $definitions[1]);
        static::assertSame('ce_test_two', $definitions[1]->getEntityName());
        static::assertInstanceOf(EntityRepository::class, $container->get($definitions[1]->getEntityName() . '.repository'));
    }

    public function testAfterMigrationWithEmptyFlags(): void
    {
        $container = new Container();

        /** @var DynamicEntityDefinition[] $definitions */
        $definitions = [
            DynamicEntityDefinition::create('ce_test_one', [], [], $container),
        ];

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn([
                [
                    'name' => 'ce_test_one',
                    'fields' => json_encode([]),
                    'flags' => '',
                ],
            ]);

        $container->set(Connection::class, $connection);
        $container->set(DefinitionInstanceRegistry::class, new DefinitionInstanceRegistry($container, [], []));
        $container->set(EntityReaderInterface::class, static::createStub(EntityReaderInterface::class));
        $container->set(VersionManager::class, static::createStub(VersionManager::class));
        $container->set(EntitySearcherInterface::class, static::createStub(EntitySearcherInterface::class));
        $container->set(EntityAggregatorInterface::class, static::createStub(EntityAggregatorInterface::class));
        $container->set('event_dispatcher', static::createStub(EventDispatcherInterface::class));
        $container->set(EntityLoadedEventFactory::class, static::createStub(EntityLoadedEventFactory::class));

        $registrar = new CustomEntityRegistrar($container);

        $registrar->register();

        static::assertInstanceOf(DynamicEntityDefinition::class, $definitions[0]);
        static::assertSame('ce_test_one', $definitions[0]->getEntityName());
        static::assertInstanceOf(EntityRepository::class, $container->get($definitions[0]->getEntityName() . '.repository'));
    }
}
