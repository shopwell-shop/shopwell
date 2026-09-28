<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\DependencyInjection\CompilerPass;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Customer\CustomerDefinition;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Field;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\FieldType;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\ManyToMany;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\OnDelete;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Translations;
use Shopwell\Core\Framework\DataAbstractionLayer\AttributeEntityCompiler;
use Shopwell\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity as EntityStruct;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEventFactory;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Read\EntityReaderInterface;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntityAggregatorInterface;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Shopwell\Core\Framework\DataAbstractionLayer\Telemetry\DalSearchInstrumentor;
use Shopwell\Core\Framework\DataAbstractionLayer\VersionManager;
use Shopwell\Core\Framework\DependencyInjection\CompilerPass\AttributeEntityCompilerPass;
use Shopwell\Core\Framework\DependencyInjection\CompilerPass\EntityCompilerPass;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\ArrayEntity;
use Shopwell\Core\System\DependencyInjection\CompilerPass\SalesChannelEntityCompilerPass;
use Shopwell\Core\System\SalesChannel\Entity\SalesChannelDefinitionInstanceRegistry;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AttributeEntityCompilerPass::class)]
class AttributeEntityCompilerPassTest extends TestCase
{
    public function testAttributeEntityDefinitionHasTag(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition(DefinitionInstanceRegistry::class, new Definition(DefinitionInstanceRegistry::class));

        $attributeEntity = new Definition(TestAttributeEntity::class);
        $attributeEntity->setPublic(true);
        $attributeEntity->addTag('shopwell.entity');
        $container->setDefinition(TestAttributeEntity::class, $attributeEntity);

        $compiler = new AttributeEntityCompiler();

        $compilerPass = new AttributeEntityCompilerPass($compiler);
        $compilerPass->process($container);

        static::assertTrue($container->hasDefinition('test_attribute_entity.definition'));
        static::assertTrue($container->getDefinition('test_attribute_entity.definition')->hasTag('shopwell.entity.definition'));

        static::assertTrue($container->hasDefinition('test_attribute_entity_translation.definition'));
        static::assertTrue($container->getDefinition('test_attribute_entity_translation.definition')->hasTag('shopwell.entity.definition'));

        static::assertTrue($container->hasDefinition('customer_test_attribute_entity.definition'));
        static::assertTrue($container->getDefinition('customer_test_attribute_entity.definition')->hasTag('shopwell.entity.definition'));
    }

    public function testAssociationsKeepResolvingThroughTheDalRegistryOnceTheSalesChannelRegistryIsBuilt(): void
    {
        $container = new ContainerBuilder();
        $container->addCompilerPass(new AttributeEntityCompilerPass(new AttributeEntityCompiler()), PassConfig::TYPE_BEFORE_OPTIMIZATION, 99);
        $container->addCompilerPass(new EntityCompilerPass());
        $container->addCompilerPass(new SalesChannelEntityCompilerPass());

        $container->setDefinition(DefinitionInstanceRegistry::class, new Definition(DefinitionInstanceRegistry::class, [new Reference('service_container'), [], []]))->setPublic(true);
        $container->setDefinition(SalesChannelDefinitionInstanceRegistry::class, new Definition(SalesChannelDefinitionInstanceRegistry::class, ['', new Reference('service_container'), [], []]))->setPublic(true);

        foreach ([EntityReaderInterface::class, VersionManager::class, EntitySearcherInterface::class, EntityAggregatorInterface::class, 'event_dispatcher', EntityLoadedEventFactory::class, DalSearchInstrumentor::class] as $repositoryDependency) {
            $container->register($repositoryDependency)->setSynthetic(true)->setPublic(true);
        }

        $container->setDefinition(CustomerDefinition::class, new Definition(CustomerDefinition::class))->addTag('shopwell.entity.definition');
        $container->setDefinition(TestAttributeEntity::class, new Definition(TestAttributeEntity::class))->setPublic(true)->addTag('shopwell.entity');

        $container->compile();

        $registry = $container->get(DefinitionInstanceRegistry::class);
        static::assertInstanceOf(DefinitionInstanceRegistry::class, $registry);
        $container->get(SalesChannelDefinitionInstanceRegistry::class);

        $customers = $registry->getByEntityName('test_attribute_entity')->getFields()->get('customers');
        static::assertInstanceOf(ManyToManyAssociationField::class, $customers);
        static::assertSame($registry->getByEntityName('customer'), $customers->getToManyReferenceDefinition());
    }
}

/**
 * @internal
 */
#[Entity('test_attribute_entity')]
class TestAttributeEntity extends EntityStruct
{
    #[PrimaryKey]
    #[Field(type: FieldType::UUID)]
    public string $id;

    #[Required]
    #[Field(type: FieldType::STRING, translated: true)]
    public string $name;

    /**
     * @var array<string, ArrayEntity>|null
     */
    #[Translations]
    public ?array $translations = null;

    /**
     * @var array<string, CustomerEntity>|null
     */
    #[ManyToMany(entity: 'customer', onDelete: OnDelete::SET_NULL)]
    public ?array $customers = null;
}
