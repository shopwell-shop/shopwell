<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity\ProductCrossSellingSerializer;
use Shopwell\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Field\FieldSerializer;
use Shopwell\Core\Content\ImportExport\DataAbstractionLayer\Serializer\SerializerRegistry;
use Shopwell\Core\Content\ImportExport\Struct\Config;
use Shopwell\Core\Content\Product\Aggregate\ProductCrossSelling\ProductCrossSellingDefinition;
use Shopwell\Core\Content\Product\Aggregate\ProductCrossSellingAssignedProducts\ProductCrossSellingAssignedProductsCollection;
use Shopwell\Core\Content\Product\Aggregate\ProductCrossSellingAssignedProducts\ProductCrossSellingAssignedProductsDefinition;
use Shopwell\Core\Content\Product\Aggregate\ProductCrossSellingAssignedProducts\ProductCrossSellingAssignedProductsEntity;
use Shopwell\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(ProductCrossSellingSerializer::class)]
class ProductCrossSellingSerializerTest extends TestCase
{
    public function testTheAssignedProductsOfARecordAreLookedUpInOneSearch(): void
    {
        $crossSellingId = Uuid::randomHex();
        $productIds = [Uuid::randomHex(), Uuid::randomHex(), Uuid::randomHex()];
        $existingAssignmentId = Uuid::randomHex();

        $existing = new ProductCrossSellingAssignedProductsCollection([
            (new ProductCrossSellingAssignedProductsEntity())->assign([
                'id' => $existingAssignmentId,
                'crossSellingId' => $crossSellingId,
                'productId' => $productIds[1],
            ]),
        ]);

        // one result per assigned product, so a per-product lookup is counted rather than running the queue dry
        $assignedProductsRepository = new StaticEntityRepository(
            [$existing, $existing, $existing],
            new ProductCrossSellingAssignedProductsDefinition()
        );

        $serializer = new ProductCrossSellingSerializer($assignedProductsRepository);
        $serializer->setRegistry(new SerializerRegistry([], [new FieldSerializer()]));

        $definition = new ProductCrossSellingDefinition();
        $definition->compile(static::createStub(DefinitionInstanceRegistry::class));

        $record = [
            'id' => $crossSellingId,
            'assignedProducts' => implode('|', $productIds),
        ];

        $result = iterator_to_array($serializer->deserialize(new Config([], [], []), $definition, $record));

        static::assertCount(1, $assignedProductsRepository->searchedCriteria);
        static::assertSame(
            [null, $existingAssignmentId, null],
            array_map(static fn (array $assigned) => $assigned['id'] ?? null, $result['assignedProducts'])
        );
    }
}
