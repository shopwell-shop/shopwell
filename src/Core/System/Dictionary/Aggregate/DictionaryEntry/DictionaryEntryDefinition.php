<?php declare(strict_types=1);

namespace Shopwell\Core\System\Dictionary\Aggregate\DictionaryEntry;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Dictionary\Aggregate\DictionaryEntry\Aggregate\DictionaryEntryTranslation\DictionaryEntryTranslationDefinition;
use Shopwell\Core\System\Dictionary\DictionaryTypeDefinition;

/**
 * @codeCoverageIgnore
 */
#[Package('fundamentals@discovery')]
class DictionaryEntryDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'dictionary_entry';

    final public const NOT_SPECIFIED = 'not_specified';

    final public const MALE = 'male';

    final public const FEMALE = 'female';

    final public const DEFAULT_POSITION = 100;

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return DictionaryEntryCollection::class;
    }

    public function getEntityClass(): string
    {
        return DictionaryEntryEntity::class;
    }

    public function since(): ?string
    {
        return '6.7.0.0';
    }

    public function getDefaults(): array
    {
        return [
            'position' => self::DEFAULT_POSITION,
        ];
    }

    protected function getParentDefinitionClass(): ?string
    {
        return DictionaryTypeDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required())->setDescription('Unique identity of the dictionary entry.'),
            (new FkField('dictionary_type_id', 'dictionaryTypeId', DictionaryTypeDefinition::class))->addFlags(new ApiAware(), new Required())->setDescription('Unique identity of the dictionary type this entry belongs to.'),
            (new StringField('technical_name', 'technicalName'))->addFlags(new ApiAware(), new Required(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING))->setDescription('Technical name of the entry, unique within its dictionary type. For example: mr'),
            (new IntField('position', 'position'))->addFlags(new ApiAware())->setDescription('Numerical value that indicates the order in which the defined entries must be displayed in the frontend.'),
            (new BoolField('active', 'active'))->addFlags(new ApiAware())->setDescription('When boolean value is `true`, the dictionary entry is available for selection.'),
            (new TranslatedField('label'))->addFlags(new ApiAware(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING))->setDescription('Human readable label of the dictionary entry.'),

            (new TranslationsAssociationField(DictionaryEntryTranslationDefinition::class, 'dictionary_entry_id'))->addFlags(new ApiAware(), new Required()),
            new ManyToOneAssociationField('dictionaryType', 'dictionary_type_id', DictionaryTypeDefinition::class, 'id', false),
        ]);
    }
}
