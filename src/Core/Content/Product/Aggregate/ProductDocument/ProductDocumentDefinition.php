<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Product\Aggregate\ProductDocument;

use Shopwell\Core\Content\Media\MediaDefinition;
use Shopwell\Core\Content\Product\Aggregate\ProductDocumentTranslation\ProductDocumentTranslationDefinition;
use Shopwell\Core\Content\Product\ProductDefinition;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Immutable;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\ReverseInherited;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Runtime;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('inventory')]
class ProductDocumentDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'product_document';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return ProductDocumentCollection::class;
    }

    public function getEntityClass(): string
    {
        return ProductDocumentEntity::class;
    }

    public function since(): ?string
    {
        return '6.7.17.0';
    }

    protected function getParentDefinitionClass(): ?string
    {
        return ProductDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required())->setDescription('Unique identity of the product document.'),
            (new VersionField())->addFlags(new ApiAware()),

            (new FkField('product_id', 'productId', ProductDefinition::class))->addFlags(new ApiAware(), new Required(), new Immutable())->setDescription('Unique identity of the product.'),
            (new ReferenceVersionField(ProductDefinition::class))->addFlags(new ApiAware(), new Required()),

            (new IntField('position', 'position'))->addFlags(new ApiAware())->setDescription('The order in which the documents of a product are displayed.'),
            (new TranslatedField('title'))->addFlags(new ApiAware())->setDescription('Display name of the document.'),
            (new TranslatedField('mediaId'))->addFlags(new ApiAware())->setDescription('Unique identity of the file of the document.'),
            (new TranslatedField('customFields'))->addFlags(new ApiAware()),

            (new ManyToOneAssociationField('product', 'product_id', ProductDefinition::class, 'id'))->addFlags(new ApiAware(AdminApiSource::class), new ReverseInherited('documents')),
            (new TranslationsAssociationField(ProductDocumentTranslationDefinition::class, 'product_document_id'))->addFlags(new ApiAware(), new Required()),
            (new OneToOneAssociationField('media', 'media', 'id', MediaDefinition::class))->addFlags(new ApiAware(), new Runtime())->setDescription('File of the document in the current language. Only set by the product documents route and the CMS slot; `null` when documents are loaded as an association.'),
        ]);
    }
}
