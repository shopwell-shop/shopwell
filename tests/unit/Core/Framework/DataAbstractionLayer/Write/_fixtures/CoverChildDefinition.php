<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\DataAbstractionLayer\Write\_fixtures;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\SetNullOnDelete;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 *
 * Cascade-deleted with its parent, and the cover of parents, like `product_media` for `product`.
 */
#[Package('framework')]
class CoverChildDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'cover_child';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new OneToManyAssociationField('coverParents', CoverParentDefinition::class, 'cover_id'))->addFlags(new SetNullOnDelete()),
        ]);
    }
}
