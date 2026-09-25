<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @internal
 */
class ReferenceVersionExtension extends EntityExtension
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            new ManyToOneAssociationField('toOne', 'to_one', ExtendedDefinition::class)
        );

        $collection->add(
            new ReferenceVersionField(ExtendedDefinition::class)
        );
    }

    public function getEntityName(): string
    {
        return 'extendable';
    }
}
