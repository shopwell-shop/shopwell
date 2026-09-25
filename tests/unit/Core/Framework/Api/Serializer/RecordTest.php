<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Api\Serializer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Customer\CustomerDefinition;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Content\Product\ProductDefinition;
use Shopwell\Core\Content\Product\ProductEntity;
use Shopwell\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionDefinition;
use Shopwell\Core\Framework\Api\Serializer\Record;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Tag\TagCollection;
use Shopwell\Core\System\Tag\TagDefinition;
use Shopwell\Core\System\Tag\TagEntity;
use Shopwell\Core\System\Tax\TaxEntity;
use Shopwell\Tests\Unit\Core\Framework\Api\Serializer\_fixtures\TestAttributeEntity;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Record::class)]
class RecordTest extends TestCase
{
    public function testSerializeJson(): void
    {
        $record = $this->generateRecord();

        static::assertEquals([
            'id' => 'product-id',
            'type' => 'product',
            'attributes' => [
                'active' => true,
                'stock' => 10,
            ],
            'links' => new \stdClass(),
            'relationships' => [
                'options' => [
                    'data' => [],
                ],
                'tags' => [
                    'data' => [],
                ],
            ],
            'meta' => [],
        ], $record->jsonSerialize());
    }

    public function testMerge(): void
    {
        $tag = (new TagEntity())->assign(['id' => 'tag-id', '_uniqueIdentifier' => 'tag-id']);
        $tax = (new TaxEntity())->assign(['id' => 'tax-id', '_uniqueIdentifier' => 'tax-id']);

        $product = (new ProductEntity())->assign(['id' => 'product-id', '_uniqueIdentifier' => 'product-id']);
        $product->setActive(false);
        $product->setStock(0);
        $product->setTags(new TagCollection([$tag]));
        $product->setTax($tax);
        $product->setCustomFields([]);

        $record = $this->generateRecord();
        $record->setAttribute('customFields', []);
        $record->merge($product);

        static::assertEquals([
            'id' => 'product-id',
            'type' => 'product',
            'attributes' => [
                'active' => false,
                'stock' => 0,
                'customFields' => new \stdClass(),
            ],
            'links' => new \stdClass(),
            'relationships' => [
                'options' => [
                    'data' => [],
                ],
                'tags' => [
                    'data' => [[
                        'type' => 'tag',
                        'id' => 'tag-id',
                    ]],
                ],
            ],
            'meta' => [],
        ], $record->jsonSerialize());
    }

    public function testMergeWithAttributeEntity(): void
    {
        $entity = (new TestAttributeEntity())->assign([
            'id' => 'entity-id',
            '_uniqueIdentifier' => 'entity-id',
            'customerId' => 'customer-id',
        ]);
        $entity->products = [
            'product-id' => (new ProductEntity())->assign(['id' => 'product-id', '_uniqueIdentifier' => 'product-id']),
        ];
        $entity->customer = (new CustomerEntity())->assign(['id' => 'customer-id', '_uniqueIdentifier' => 'customer-id']);

        $productDefinition = $this->createMock(ProductDefinition::class);
        $productDefinition->expects($this->once())
            ->method('getEntityName')
            ->willReturn('product');

        $customerDefinition = $this->createMock(CustomerDefinition::class);
        $customerDefinition->expects($this->once())
            ->method('getEntityName')
            ->willReturn('customer');

        $record = new Record('entity-id', 'test_attribute_entity');
        $record->setAttribute('customerId', 'customer-id');
        $record->addRelationship('products', [
            'tmp' => [
                'definition' => $productDefinition,
            ],
            'data' => [],
        ]);
        $record->addRelationship('customer', [
            'tmp' => [
                'definition' => $customerDefinition,
            ],
            'data' => [],
        ]);
        $record->merge($entity);

        static::assertSame([
            'products' => [
                'tmp' => [
                    'definition' => $productDefinition,
                ],
                'data' => [
                    [
                        'type' => 'product',
                        'id' => 'product-id',
                    ],
                ],
            ],
            'customer' => [
                'tmp' => [
                    'definition' => $customerDefinition,
                ],
                'data' => [
                    'type' => 'customer',
                    'id' => 'customer-id',
                ],
            ],
        ], $record->getRelationships());
    }

    private function generateRecord(): Record
    {
        $record = new Record('product-id', 'product');
        $record->setAttribute('active', true);
        $record->setAttribute('stock', 10);

        $propertyGroupOptionDefinition = static::createStub(PropertyGroupOptionDefinition::class);
        $propertyGroupOptionDefinition->method('getEntityName')
            ->willReturn('property_group_option');

        $record->addRelationship('options', [
            'tmp' => [
                'definition' => $propertyGroupOptionDefinition,
            ],
            'data' => [],
        ]);

        $tagDefinition = static::createStub(TagDefinition::class);
        $tagDefinition->method('getEntityName')
            ->willReturn('tag');

        $record->addRelationship('tags', [
            'tmp' => [
                'definition' => $tagDefinition,
            ],
            'data' => [],
        ]);

        return $record;
    }
}
