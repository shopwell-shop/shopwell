<?php declare(strict_types=1);

namespace Shopwell\Core\Content\ProductStream;

use Shopwell\Core\Content\Category\CategoryDefinition;
use Shopwell\Core\Content\Product\Aggregate\ProductCrossSelling\ProductCrossSellingDefinition;
use Shopwell\Core\Content\Product\Aggregate\ProductStreamMapping\ProductStreamMappingDefinition;
use Shopwell\Core\Content\Product\ProductDefinition;
use Shopwell\Core\Content\ProductExport\ProductExportDefinition;
use Shopwell\Core\Content\ProductStream\Aggregate\ProductStreamFilter\ProductStreamFilterDefinition;
use Shopwell\Core\Content\ProductStream\Aggregate\ProductStreamTranslation\ProductStreamTranslationDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('inventory')]
class ProductStreamDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'product_stream';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getDefaults(): array
    {
        return [
            'internal' => false,
            'displayAsGroup' => true,
        ];
    }

    public function getCollectionClass(): string
    {
        return ProductStreamCollection::class;
    }

    public function getEntityClass(): string
    {
        return ProductStreamEntity::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    public function getHydratorClass(): string
    {
        return ProductStreamHydrator::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required())->setDescription('Unique identity of product stream.'),
            (new JsonField('api_filter', 'apiFilter'))->addFlags(new WriteProtected())->setDescription('Internal field.'),
            (new BoolField('invalid', 'invalid'))->addFlags(new WriteProtected())->setDescription('When the boolean value is `true`, the ProductStream is no more available for usage.'),

            (new TranslatedField('name'))->addFlags(new ApiAware(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            (new TranslatedField('description'))->addFlags(new ApiAware()),
            (new TranslatedField('customFields'))->addFlags(new ApiAware()),
            (new BoolField('internal', 'internal'))->addFlags(new ApiAware())->setDescription('When the boolean value is `true` indicating that it is for internal use only and will not appear in product stream listings.'),
            (new BoolField('display_as_group', 'displayAsGroup'))->addFlags(new ApiAware())->setDescription('When enabled, matching variants are grouped according to the product presentation setting. Disable to show them individually.'),
            (new TranslationsAssociationField(ProductStreamTranslationDefinition::class, 'product_stream_id'))->addFlags(new Required()),
            (new OneToManyAssociationField('filters', ProductStreamFilterDefinition::class, 'product_stream_id'))->addFlags(new CascadeDelete()),
            new OneToManyAssociationField('productCrossSellings', ProductCrossSellingDefinition::class, 'product_stream_id'),
            (new OneToManyAssociationField('productExports', ProductExportDefinition::class, 'product_stream_id', 'id'))->addFlags(new RestrictDelete()),
            new OneToManyAssociationField('categories', CategoryDefinition::class, 'product_stream_id'),

            (new ManyToManyAssociationField('products', ProductDefinition::class, ProductStreamMappingDefinition::class, 'product_stream_id', 'product_id'))->addFlags(new CascadeDelete())->setDescription('Products this product stream includes'),
        ]);
    }
}
