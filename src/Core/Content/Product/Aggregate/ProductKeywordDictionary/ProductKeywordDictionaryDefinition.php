<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Product\Aggregate\ProductKeywordDictionary;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Computed;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Language\LanguageDefinition;

/**
 * @codeCoverageIgnore
 */
#[Package('inventory')]
class ProductKeywordDictionaryDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'product_keyword_dictionary';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return ProductKeywordDictionaryCollection::class;
    }

    public function getEntityClass(): string
    {
        return ProductKeywordDictionaryEntity::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    public function getHydratorClass(): string
    {
        return ProductKeywordDictionaryHydrator::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required())->setDescription('Unique identity of product keyword.'),
            (new FkField('language_id', 'languageId', LanguageDefinition::class))->addFlags(new PrimaryKey(), new ApiAware(), new Required())->setDescription('Unique identity of the language.'),

            (new StringField('keyword', 'keyword'))->addFlags(new ApiAware(), new Required())->setDescription('The keywords that help to search the product.'),
            (new StringField('reversed', 'reversed'))->addFlags(new Computed())->setDescription('The keywords are revered for the search.'),
            new ManyToOneAssociationField('language', 'language_id', LanguageDefinition::class, 'id', false),
        ]);
    }

    protected function defaultFields(): array
    {
        return [];
    }
}
