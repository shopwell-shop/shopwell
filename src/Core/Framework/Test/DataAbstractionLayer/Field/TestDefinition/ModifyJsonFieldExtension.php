<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @internal
 */
class ModifyJsonFieldExtension extends EntityExtension
{
    public function modifyFields(FieldCollection $collection): void
    {
        $data = $collection->get('data');
        if (!$data instanceof JsonField) {
            return;
        }

        $data->addPropertyMapping(new JsonField('extended', 'extended', [
            new IntField('maxSuggestCount', 'maxSuggestCount'),
            new IntField('maxSearchCount', 'maxSearchCount'),
        ]));
    }

    public function getEntityName(): string
    {
        return NestedDefinition::ENTITY_NAME;
    }
}
