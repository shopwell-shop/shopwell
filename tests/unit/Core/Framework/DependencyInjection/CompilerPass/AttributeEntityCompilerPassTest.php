<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\DependencyInjection\CompilerPass;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
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
use Shopwell\Core\Framework\DependencyInjection\CompilerPass\AttributeEntityCompilerPass;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\ArrayEntity;
use Shopwell\Core\System\SalesChannel\Entity\SalesChannelDefinitionInstanceRegistry;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

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
        $container->setDefinition(SalesChannelDefinitionInstanceRegistry::class, new Definition(SalesChannelDefinitionInstanceRegistry::class));

        $attributeEntity = new Definition(TestAttributeEntity::class);
        $attributeEntity->setPublic(true);
        $attributeEntity->addTag('shopware.entity');
        $container->setDefinition(TestAttributeEntity::class, $attributeEntity);

        $compiler = new AttributeEntityCompiler();

        $compilerPass = new AttributeEntityCompilerPass($compiler);
        $compilerPass->process($container);

        static::assertTrue($container->hasDefinition('test_attribute_entity.definition'));
        static::assertTrue($container->getDefinition('test_attribute_entity.definition')->hasTag('shopware.entity.definition'));

        static::assertTrue($container->hasDefinition('test_attribute_entity_translation.definition'));
        static::assertTrue($container->getDefinition('test_attribute_entity_translation.definition')->hasTag('shopware.entity.definition'));

        static::assertTrue($container->hasDefinition('customer_test_attribute_entity.definition'));
        static::assertTrue($container->getDefinition('customer_test_attribute_entity.definition')->hasTag('shopware.entity.definition'));
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
