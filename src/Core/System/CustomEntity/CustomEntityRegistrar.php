<?php declare(strict_types=1);

namespace Shopwell\Core\System\CustomEntity;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Shopwell\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEventFactory;
use Shopwell\Core\Framework\DataAbstractionLayer\Read\EntityReaderInterface;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntityAggregatorInterface;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Shopwell\Core\Framework\DataAbstractionLayer\VersionManager;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\CustomEntity\Schema\DynamicEntityDefinition;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @internal
 */
#[Package('framework')]
class CustomEntityRegistrar
{
    public function __construct(private readonly ContainerInterface $container)
    {
    }

    public function register(): void
    {
        try {
            $entities = $this->container->get(Connection::class)->fetchAllAssociative('
                SELECT custom_entity.name, custom_entity.fields, custom_entity.flags
                FROM custom_entity
                    LEFT JOIN app ON app.id = custom_entity.app_id
                WHERE (custom_entity.app_id IS NULL OR app.active = 1) AND custom_entity.deleted_at IS NULL;
            ');
        } catch (Exception) {
            // kernel booted without database connection, or booted for migration and custom entity table not created yet
            return;
        }

        $definitions = [];
        $registry = $this->container->get(DefinitionInstanceRegistry::class);

        foreach ($entities as $entity) {
            $fields = json_decode((string) $entity['fields'], true, 512, \JSON_THROW_ON_ERROR);

            try {
                $flags = json_decode((string) $entity['flags'], true, 512, \JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                $flags = [];
            }

            $definition = DynamicEntityDefinition::create($entity['name'], $fields, $flags, $this->container);

            $definitions[] = $definition;

            $this->container->set($definition->getEntityName(), $definition);
            $this->container->set($definition->getEntityName() . '.repository', self::createRepository($this->container, $definition));
            $registry->register($definition, $definition->getEntityName());
        }

        foreach ($definitions as $definition) {
            // triggers field generation to generate reverse foreign keys, translation definitions and mapping definitions
            $definition->getFields();
        }
    }

    /**
     * @return EntityRepository<EntityCollection<Entity>>
     */
    public static function createRepository(ContainerInterface $container, EntityDefinition $definition): EntityRepository
    {
        return EntityRepository::createLazyGhost(static function (EntityRepository $instance) use ($definition, $container): void {
            // @phpstan-ignore constructor.call (We have to call the __construct function on the instance and not create a completly new instance)
            $instance->__construct(
                $definition,
                $container->get(EntityReaderInterface::class),
                $container->get(VersionManager::class),
                $container->get(EntitySearcherInterface::class),
                $container->get(EntityAggregatorInterface::class),
                $container->get('event_dispatcher'),
                $container->get(EntityLoadedEventFactory::class)
            );
        });
    }
}
