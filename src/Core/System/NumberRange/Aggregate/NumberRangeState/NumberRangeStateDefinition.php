<?php declare(strict_types=1);

namespace Shopwell\Core\System\NumberRange\Aggregate\NumberRangeState;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\NumberRange\NumberRangeDefinition;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
class NumberRangeStateDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'number_range_state';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return NumberRangeStateCollection::class;
    }

    public function getEntityClass(): string
    {
        return NumberRangeStateEntity::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function getParentDefinitionClass(): string
    {
        return NumberRangeDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required())->setDescription('Unique identity of number range\'s state.'),
            (new FkField('number_range_id', 'numberRangeId', NumberRangeDefinition::class))->addFlags(new PrimaryKey(), new Required())->setDescription('Unique identity of number range.'),
            (new IntField('last_value', 'lastValue'))->addFlags(new Required()),

            (new OneToOneAssociationField('numberRange', 'number_range_id', 'id', NumberRangeDefinition::class, false))->addFlags(new RestrictDelete()),
        ]);
    }
}
