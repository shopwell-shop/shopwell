<?php declare(strict_types=1);

namespace Shopwell\Core\System\Dictionary;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Dictionary\Aggregate\DictionaryEntry\DictionaryEntryDefinition;
use Shopwell\Core\System\Dictionary\Aggregate\DictionaryTypeTranslation\DictionaryTypeTranslationDefinition;

/**
 * @codeCoverageIgnore
 */
#[Package('fundamentals@discovery')]
class DictionaryTypeDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'dictionary_type';

    final public const TYPE_SALUTATION = 'salutation';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return DictionaryTypeCollection::class;
    }

    public function getEntityClass(): string
    {
        return DictionaryTypeEntity::class;
    }

    public function since(): ?string
    {
        return '6.7.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required())->setDescription('Unique identity of the dictionary type.'),
            (new StringField('technical_name', 'technicalName'))->addFlags(new ApiAware(), new Required(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING))->setDescription('Unique technical name of the dictionary type. For example: salutation'),
            (new TranslatedField('label'))->addFlags(new ApiAware(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING))->setDescription('Human readable label of the dictionary type.'),

            (new TranslationsAssociationField(DictionaryTypeTranslationDefinition::class, 'dictionary_type_id'))->addFlags(new ApiAware(), new Required()),

            (new OneToManyAssociationField('entries', DictionaryEntryDefinition::class, 'dictionary_type_id', 'id'))->addFlags(new ApiAware(), new CascadeDelete()),
        ]);
    }
}
