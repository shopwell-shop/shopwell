<?php declare(strict_types=1);

namespace Shopwell\Core\System\NumberRange;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\NumberRange\Aggregate\NumberRangeSalesChannel\NumberRangeSalesChannelDefinition;
use Shopwell\Core\System\NumberRange\Aggregate\NumberRangeState\NumberRangeStateDefinition;
use Shopwell\Core\System\NumberRange\Aggregate\NumberRangeTranslation\NumberRangeTranslationDefinition;
use Shopwell\Core\System\NumberRange\Aggregate\NumberRangeType\NumberRangeTypeDefinition;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
class NumberRangeDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'number_range';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return NumberRangeCollection::class;
    }

    public function getEntityClass(): string
    {
        return NumberRangeEntity::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required())->setDescription('Unique identity of number range.'),

            (new FkField('type_id', 'typeId', NumberRangeTypeDefinition::class))->addFlags(new Required())->setDescription('Unique identity of type.'),
            (new BoolField('global', 'global'))->addFlags(new Required())->setDescription('When set to `true`, the defined number range is used across all sales channels.'),
            new TranslatedField('name'),
            new TranslatedField('description'),
            (new StringField('pattern', 'pattern'))->addFlags(new Required())->setDescription('Custom formatting in order to include for example, the date in the number range.'),
            (new IntField('start', 'start'))->addFlags(new Required())->setDescription('If the start number were 12345, the following patterns would give the following numbers: Order{n}-{date}: Order12345-2019-05-23, Order{n}-{date_d.m.Y}: Order12345-23.05.2019'),
            new TranslatedField('customFields'),

            new ManyToOneAssociationField('type', 'type_id', NumberRangeTypeDefinition::class),
            (new OneToManyAssociationField('numberRangeSalesChannels', NumberRangeSalesChannelDefinition::class, 'number_range_id'))->addFlags(new CascadeDelete()),
            (new OneToOneAssociationField('state', 'id', 'number_range_id', NumberRangeStateDefinition::class, false))->addFlags(new CascadeDelete()),
            (new TranslationsAssociationField(NumberRangeTranslationDefinition::class, 'number_range_id'))->addFlags(new Required()),
        ]);
    }
}
