<?php

declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Api\Controller\Fixtures\ApiController;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @internal
 */
class ChildDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'child_entity';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new OneToOneAssociationField('firstChildOneToOneParent', 'id', 'first_child_one_to_one_id', ParentDefinition::class, false),
            new OneToOneAssociationField('secondChildOneToOneParent', 'id', 'second_child_one_to_one_id', ParentDefinition::class, false),

            new OneToManyAssociationField('firstManyToOneParents', ParentDefinition::class, 'first_child_many_to_one_id'),
            new OneToManyAssociationField('secondManyToOneParents', ParentDefinition::class, 'second_child_many_to_one_id'),

            new FkField('first_parent_one_to_many_id', 'firstParentOneToManyId', ParentDefinition::class),
            new ManyToOneAssociationField('firstParentOneToMany', 'first_parent_one_to_many_id', ParentDefinition::class, 'id', false),
            new FkField('second_parent_one_to_many_id', 'secondParentOneToManyId', ParentDefinition::class),
            new ManyToOneAssociationField('secondParentOneToMany', 'second_parent_one_to_many_id', ParentDefinition::class, 'id', false),
        ]);
    }
}
