<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\DataAbstractionLayer\Dbal;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Category\Aggregate\CategoryTranslation\CategoryTranslationDefinition;
use Shopwell\Core\Content\Category\CategoryCollection;
use Shopwell\Core\Content\Category\CategoryDefinition;
use Shopwell\Core\Content\Product\Aggregate\ProductCategory\ProductCategoryDefinition;
use Shopwell\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopwell\Core\Content\Product\Aggregate\ProductManufacturerTranslation\ProductManufacturerTranslationDefinition;
use Shopwell\Core\Content\Product\Aggregate\ProductTranslation\ProductTranslationDefinition;
use Shopwell\Core\Content\Product\ProductCollection;
use Shopwell\Core\Content\Product\ProductDefinition;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopwell\Core\Framework\DataAbstractionLayer\PartialEntity;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Tax\TaxDefinition;

/**
 * @internal
 */
#[Package('framework')]
class ManyToManyAssociationFieldTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository<ProductCollection>
     */
    private EntityRepository $productRepository;

    private Context $context;

    /**
     * @var EntityRepository<CategoryCollection>
     */
    private EntityRepository $categoryRepository;

    protected function setUp(): void
    {
        $this->productRepository = static::getContainer()->get('product.repository');
        $this->categoryRepository = static::getContainer()->get('category.repository');
        $this->context = Context::createDefaultContext();
    }

    public function testWriteWithoutData(): void
    {
        $categoryId = Uuid::randomHex();
        $data = [
            'id' => $categoryId,
            'name' => 'test',
        ];

        $this->categoryRepository->create([$data], $this->context);

        $productId = Uuid::randomHex();
        $data = [
            'id' => $productId,
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'test',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'categories' => [
                ['id' => $categoryId],
            ],
        ];

        $writtenEvent = $this->productRepository->create([$data], $this->context);

        static::assertInstanceOf(EntityWrittenEvent::class, $writtenEvent->getEventByEntityName(TaxDefinition::ENTITY_NAME));
        static::assertInstanceOf(EntityWrittenEvent::class, $writtenEvent->getEventByEntityName(ProductManufacturerDefinition::ENTITY_NAME));
        static::assertInstanceOf(EntityWrittenEvent::class, $writtenEvent->getEventByEntityName(ProductCategoryDefinition::ENTITY_NAME));
        static::assertInstanceOf(EntityWrittenEvent::class, $writtenEvent->getEventByEntityName(ProductManufacturerTranslationDefinition::ENTITY_NAME));
        static::assertInstanceOf(EntityWrittenEvent::class, $writtenEvent->getEventByEntityName(ProductDefinition::ENTITY_NAME));
        static::assertInstanceOf(EntityWrittenEvent::class, $writtenEvent->getEventByEntityName(ProductTranslationDefinition::ENTITY_NAME));
        static::assertNotNull($writtenEvent->getEventByEntityName(CategoryDefinition::ENTITY_NAME));
        static::assertNull($writtenEvent->getEventByEntityName(CategoryTranslationDefinition::ENTITY_NAME));
    }

    public function testWriteWithData(): void
    {
        $id = Uuid::randomHex();
        $data = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'test',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'categories' => [
                ['id' => $id, 'name' => 'asd'],
            ],
        ];

        $writtenEvent = $this->productRepository->create([$data], $this->context);

        static::assertInstanceOf(EntityWrittenEvent::class, $writtenEvent->getEventByEntityName(TaxDefinition::ENTITY_NAME));
        static::assertInstanceOf(EntityWrittenEvent::class, $writtenEvent->getEventByEntityName(CategoryDefinition::ENTITY_NAME));
        static::assertInstanceOf(EntityWrittenEvent::class, $writtenEvent->getEventByEntityName(CategoryTranslationDefinition::ENTITY_NAME));
        static::assertInstanceOf(EntityWrittenEvent::class, $writtenEvent->getEventByEntityName(ProductManufacturerDefinition::ENTITY_NAME));
        static::assertInstanceOf(EntityWrittenEvent::class, $writtenEvent->getEventByEntityName(ProductManufacturerTranslationDefinition::ENTITY_NAME));
        static::assertInstanceOf(EntityWrittenEvent::class, $writtenEvent->getEventByEntityName(ProductCategoryDefinition::ENTITY_NAME));
        static::assertInstanceOf(EntityWrittenEvent::class, $writtenEvent->getEventByEntityName(ProductDefinition::ENTITY_NAME));
        static::assertInstanceOf(EntityWrittenEvent::class, $writtenEvent->getEventByEntityName(ProductTranslationDefinition::ENTITY_NAME));
    }

    public function testReadPartialWithoutAssociationFields(): void
    {
        $id = Uuid::randomHex();
        $data = [
            'id' => $id,
            'name' => 'test',
            'productNumber' => $id,
            'stock' => 1,
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false],
            ],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
        ];

        $this->productRepository->create([$data], Context::createDefaultContext());

        $criteria = new Criteria([$id]);
        $criteria->addFields(['name']);
        $criteria->addAssociation('properties');

        $product = $this->productRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();

        static::assertInstanceOf(PartialEntity::class, $product);
        static::assertSame('test', $product->get('name'));
        static::assertNull($product->get('properties'));
    }

    public function testReadPartialWithAssociationFields(): void
    {
        $id = Uuid::randomHex();
        $propertyId = Uuid::randomHex();
        $groupId = Uuid::randomHex();

        $data = [
            'id' => $id,
            'name' => 'test',
            'productNumber' => $id,
            'stock' => 1,
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false],
            ],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'properties' => [
                [
                    'id' => $propertyId,
                    'name' => 'Propertyname',
                    'group' => [
                        'id' => $groupId,
                        'name' => 'Groupname',
                        'customFields' => [
                            'key' => 'value',
                        ],
                    ],
                ],
            ],
            'cover' => [
                'position' => -1,
                'media' => ['fileName' => 'myFile'],
            ],
        ];

        $this->productRepository->create([$data], Context::createDefaultContext());

        $criteria = new Criteria([$id]);
        $criteria->addFields(['productNumber', 'properties.name', 'properties.group.customFields', 'cover.media.fileName']);

        $product = $this->productRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();
        static::assertInstanceOf(PartialEntity::class, $product);

        $properties = $product->get('properties');
        static::assertInstanceOf(EntityCollection::class, $properties);

        $property = $properties->first();
        static::assertInstanceOf(PartialEntity::class, $property);

        $group = $property->get('group');
        static::assertInstanceOf(PartialEntity::class, $group);

        $cover = $product->get('cover');
        static::assertInstanceOf(PartialEntity::class, $cover);

        $media = $cover->get('media');
        static::assertInstanceOf(PartialEntity::class, $media);

        static::assertSame($id, $product->get('productNumber'));
        static::assertFalse($product->has('name'));
        static::assertFalse($product->has('customFields'));

        static::assertSame($propertyId, $property->getId());
        static::assertSame('Propertyname', $property->get('name'));
        static::assertFalse($property->has('customFields'));

        static::assertSame($groupId, $group->getId());
        static::assertFalse($group->has('name'));
        static::assertSame('value', $group->get('customFields')['key']);

        static::assertSame('myFile', $media->get('fileName'));
    }
}
