<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\DataAbstractionLayer;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopwell\Core\Content\Product\ProductDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\DataAbstractionLayer\Field\DataAbstractionLayerFieldTestBehaviour;
use Shopwell\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\ExtendedProductDefinition;
use Shopwell\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\ExtendedProductManufacturerDefinition;
use Shopwell\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\ProductExtension;
use Shopwell\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\ProductManufacturerExtension;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\System\SalesChannel\Entity\SalesChannelDefinitionInstanceRegistry;

/**
 * @internal
 */
#[Package('framework')]
class EntityExtensionRegisterTest extends TestCase
{
    use DataAbstractionLayerFieldTestBehaviour {
        tearDown as protected tearDownDefinitions;
    }
    use KernelTestBehaviour;

    protected function tearDown(): void
    {
        $this->tearDownDefinitions();
        // reboot kernel to create a new container since we manipulated the original one
        KernelLifecycleManager::bootKernel();
        parent::tearDown();
    }

    public function testAddEntityExtensionToEntityWhichAlsoHasSalesChannelDefinition(): void
    {
        $this->registerDefinition(ExtendedProductDefinition::class);
        $this->registerDefinitionWithExtensions(ProductDefinition::class, ProductExtension::class);

        $fields = static::getContainer()
            ->get(DefinitionInstanceRegistry::class)
            ->get(ProductDefinition::class)
            ->getFields();
        static::assertTrue($fields->has('toOne'));
        static::assertInstanceOf(OneToOneAssociationField::class, $fields->get('toOne'));
        static::assertTrue($fields->has('oneToMany'));
        static::assertInstanceOf(OneToManyAssociationField::class, $fields->get('oneToMany'));

        $this->registerSalesChannelDefinition(ExtendedProductDefinition::class);
        $this->registerSalesChannelDefinitionWithExtensions(ProductDefinition::class, ProductExtension::class);
        $fields = static::getContainer()
            ->get(SalesChannelDefinitionInstanceRegistry::class)
            ->get(ProductDefinition::class)
            ->getFields();
        static::assertTrue($fields->has('toOne'));
        static::assertInstanceOf(OneToOneAssociationField::class, $fields->get('toOne'));
        static::assertTrue($fields->has('oneToMany'));
        static::assertInstanceOf(OneToManyAssociationField::class, $fields->get('oneToMany'));
    }

    public function testAddEntityExtensionToEntityWhichDoesNotHasSalesChannelDefinition(): void
    {
        $this->registerDefinition(ExtendedProductManufacturerDefinition::class);
        $this->registerDefinitionWithExtensions(ProductManufacturerDefinition::class, ProductManufacturerExtension::class);

        $fields = static::getContainer()
            ->get(DefinitionInstanceRegistry::class)
            ->get(ProductManufacturerDefinition::class)
            ->getFields();
        static::assertTrue($fields->has('toOne'));
        static::assertInstanceOf(OneToOneAssociationField::class, $fields->get('toOne'));
        static::assertTrue($fields->has('oneToMany'));
        static::assertInstanceOf(OneToManyAssociationField::class, $fields->get('oneToMany'));

        $this->registerSalesChannelDefinition(ExtendedProductManufacturerDefinition::class);
        $this->registerSalesChannelDefinitionWithExtensions(ProductManufacturerDefinition::class, ProductManufacturerExtension::class);
        $fields = static::getContainer()
            ->get(SalesChannelDefinitionInstanceRegistry::class)
            ->get(ProductManufacturerDefinition::class)
            ->getFields();
        static::assertTrue($fields->has('toOne'));
        static::assertInstanceOf(OneToOneAssociationField::class, $fields->get('toOne'));
        static::assertTrue($fields->has('oneToMany'));
        static::assertInstanceOf(OneToManyAssociationField::class, $fields->get('oneToMany'));
    }
}
