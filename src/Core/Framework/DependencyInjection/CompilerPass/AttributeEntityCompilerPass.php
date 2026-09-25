<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\DependencyInjection\CompilerPass;

use Shopwell\Core\Framework\DataAbstractionLayer\AttributeEntityCompiler;
use Shopwell\Core\Framework\DataAbstractionLayer\AttributeEntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\AttributeMappingDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\AttributeTranslationDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEventFactory;
use Shopwell\Core\Framework\DataAbstractionLayer\Read\EntityReaderInterface;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntityAggregatorInterface;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Shopwell\Core\Framework\DataAbstractionLayer\VersionManager;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\Entity\SalesChannelDefinitionInstanceRegistry;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

#[Package('framework')]
class AttributeEntityCompilerPass implements CompilerPassInterface
{
    public function __construct(private readonly AttributeEntityCompiler $compiler)
    {
    }

    public function process(ContainerBuilder $container): void
    {
        $services = $container->findTaggedServiceIds('shopwell.entity');

        foreach ($services as $class => $_) {
            /** @var class-string<Entity> $class */
            $definitions = $this->compiler->compile($class);

            foreach ($definitions as $definition) {
                if ($definition['type'] === 'entity') {
                    $this->definition($definition, $container, $definition['entity_name']);

                    $this->repository($container, $definition['entity_name']);

                    $this->translation($definition, $container, $definition['entity_name']);

                    continue;
                }

                if ($definition['type'] === 'mapping') {
                    $this->mapping($definition, $container);
                }
            }
        }
    }

    /**
     * @param array<string, mixed> $meta
     */
    public function definition(array $meta, ContainerBuilder $container, string $entity): void
    {
        $definition = new Definition(AttributeEntityDefinition::class);
        $definition->addArgument($meta);
        $definition->setPublic(true);
        $definition->addTag('shopwell.entity.definition');
        $container->setDefinition($entity . '.definition', $definition);

        $registry = $container->getDefinition(DefinitionInstanceRegistry::class);
        $salesChannelRegistry = $container->getDefinition(SalesChannelDefinitionInstanceRegistry::class);

        $registry->addMethodCall('register', [new Reference($entity . '.definition'), $entity . '.definition']);
        $salesChannelRegistry->addMethodCall('register', [new Reference($entity . '.definition'), 'sales_channel_definition.' . $entity . '.definition']);
    }

    private function repository(ContainerBuilder $container, string $entity): void
    {
        $repository = new Definition(
            EntityRepository::class,
            [
                new Reference($entity . '.definition'),
                new Reference(EntityReaderInterface::class),
                new Reference(VersionManager::class),
                new Reference(EntitySearcherInterface::class),
                new Reference(EntityAggregatorInterface::class),
                new Reference('event_dispatcher'),
                new Reference(EntityLoadedEventFactory::class),
            ]
        );
        $repository->setPublic(true);

        $container->setDefinition($entity . '.repository', $repository);
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function translation(array $meta, ContainerBuilder $container, string $entity): void
    {
        if (!$this->hasTranslation($meta)) {
            return;
        }

        $definition = new Definition(AttributeTranslationDefinition::class);
        $definition->addArgument($meta);
        $definition->setPublic(true);
        $definition->addTag('shopwell.entity.definition');
        $container->setDefinition($entity . '_translation.definition', $definition);

        $registry = $container->getDefinition(DefinitionInstanceRegistry::class);
        $salesChannelRegistry = $container->getDefinition(SalesChannelDefinitionInstanceRegistry::class);

        $registry->addMethodCall('register', [new Reference($entity . '_translation.definition'), $entity . '_translation.definition']);
        $salesChannelRegistry->addMethodCall('register', [new Reference($entity . '_translation.definition'), 'sales_channel_definition.' . $entity . '_translation.definition']);

        $this->repository($container, $entity . '_translation');
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function hasTranslation(array $meta): bool
    {
        /** @var array<string, mixed> $field */
        foreach ($meta['fields'] as $field) {
            if (isset($field['translated']) && $field['translated']) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function mapping(array $meta, ContainerBuilder $container): void
    {
        $definition = new Definition(AttributeMappingDefinition::class);
        $definition->addArgument($meta);
        $definition->setPublic(true);
        $definition->addTag('shopwell.entity.definition');
        $container->setDefinition($meta['entity_name'] . '.definition', $definition);

        $registry = $container->getDefinition(DefinitionInstanceRegistry::class);
        $salesChannelRegistry = $container->getDefinition(SalesChannelDefinitionInstanceRegistry::class);

        $registry->addMethodCall('register', [new Reference($meta['entity_name'] . '.definition'), $meta['entity_name'] . '.definition']);
        $salesChannelRegistry->addMethodCall('register', [new Reference($meta['entity_name'] . '.definition'), 'sales_channel_definition.' . $meta['entity_name'] . '.definition']);

        $this->repository($container, $meta['entity_name']);
    }
}
