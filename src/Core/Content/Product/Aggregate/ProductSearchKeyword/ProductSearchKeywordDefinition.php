<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Product\Aggregate\ProductSearchKeyword;

use Shopwell\Core\Content\Product\ProductDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Language\LanguageDefinition;

/**
 * @codeCoverageIgnore
 */
#[Package('inventory')]
class ProductSearchKeywordDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'product_search_keyword';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return ProductSearchKeywordCollection::class;
    }

    public function getEntityClass(): string
    {
        return ProductSearchKeywordEntity::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    public function getHydratorClass(): string
    {
        return ProductSearchKeywordHydrator::class;
    }

    protected function getParentDefinitionClass(): ?string
    {
        return ProductDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required())->setDescription('Unique identity of Product Search Keyword.'),
            new VersionField(),
            (new FkField('language_id', 'languageId', LanguageDefinition::class))->addFlags(new PrimaryKey(), new Required())->setDescription('Unique identity of language.'),
            (new FkField('product_id', 'productId', ProductDefinition::class))->addFlags(new Required())->setDescription('Unique identity of Product.'),
            (new ReferenceVersionField(ProductDefinition::class))->addFlags(new Required()),
            (new StringField('keyword', 'keyword'))->addFlags(new Required())->setDescription('The keywords that help to search the product.'),
            (new FloatField('ranking', 'ranking'))->addFlags(new Required())->setDescription('Search ranking.'),
            new ManyToOneAssociationField('product', 'product_id', ProductDefinition::class, 'id', false),
            new ManyToOneAssociationField('language', 'language_id', LanguageDefinition::class, 'id', false),
        ]);
    }
}
