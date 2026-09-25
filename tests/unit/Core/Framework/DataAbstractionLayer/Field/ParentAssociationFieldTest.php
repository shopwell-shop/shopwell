<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\DataAbstractionLayer\Field;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Category\CategoryDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ParentAssociationField;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ParentAssociationField::class)]
class ParentAssociationFieldTest extends TestCase
{
    public function testConstructorConfiguresTheParentAssociation(): void
    {
        $field = new ParentAssociationField(CategoryDefinition::class, 'id');

        static::assertSame('parent', $field->getPropertyName());
        static::assertSame('parent_id', $field->getStorageName());
        static::assertSame(CategoryDefinition::class, $field->getReferenceClass());
        static::assertSame('id', $field->getReferenceField());
    }
}
