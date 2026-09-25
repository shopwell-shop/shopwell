<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\DataAbstractionLayer\Field;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Category\CategoryDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ChildrenAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ChildrenAssociationField::class)]
class ChildrenAssociationFieldTest extends TestCase
{
    public function testConstructorConfiguresTheParentIdAssociation(): void
    {
        $field = new ChildrenAssociationField(CategoryDefinition::class);

        static::assertSame('children', $field->getPropertyName());
        static::assertSame(CategoryDefinition::class, $field->getReferenceClass());
        static::assertSame('parent_id', $field->getReferenceField());
        static::assertSame('id', $field->getLocalField());
        static::assertTrue($field->is(CascadeDelete::class));
    }
}
