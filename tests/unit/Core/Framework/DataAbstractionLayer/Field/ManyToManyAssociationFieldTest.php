<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\DataAbstractionLayer\Field;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Category\CategoryDefinition;
use Shopwell\Core\Content\Product\Aggregate\ProductCategory\ProductCategoryDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ManyToManyAssociationField::class)]
class ManyToManyAssociationFieldTest extends TestCase
{
    public function testConstructorConfiguresTheMappingColumns(): void
    {
        $field = new ManyToManyAssociationField(
            'categories',
            CategoryDefinition::class,
            ProductCategoryDefinition::class,
            'product_id',
            'category_id'
        );

        static::assertSame('categories', $field->getPropertyName());
        static::assertSame(ProductCategoryDefinition::class, $field->getReferenceClass());
        static::assertSame('id', $field->getReferenceField());
        static::assertSame('product_id', $field->getMappingLocalColumn());
        static::assertSame('category_id', $field->getMappingReferenceColumn());
        static::assertSame('id', $field->getLocalField());
    }
}
