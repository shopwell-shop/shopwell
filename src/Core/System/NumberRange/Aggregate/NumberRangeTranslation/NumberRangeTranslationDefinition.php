<?php declare(strict_types=1);

namespace Shopwell\Core\System\NumberRange\Aggregate\NumberRangeTranslation;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\NumberRange\NumberRangeDefinition;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
class NumberRangeTranslationDefinition extends EntityTranslationDefinition
{
    final public const ENTITY_NAME = 'number_range_translation';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return NumberRangeTranslationCollection::class;
    }

    public function getEntityClass(): string
    {
        return NumberRangeTranslationEntity::class;
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
            (new StringField('name', 'name'))->addFlags(new Required()),
            new StringField('description', 'description'),
            new CustomFields(),
        ]);
    }
}
