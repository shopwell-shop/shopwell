<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Document\Aggregate\DocumentType;

use Shopwell\Core\Checkout\Document\Aggregate\DocumentTypeTranslation\DocumentTypeTranslationDefinition;
use Shopwell\Core\Checkout\DocumentV2\Aggregate\DocumentBaseConfig\DocumentBaseConfigDefinition;
use Shopwell\Core\Checkout\DocumentV2\Aggregate\DocumentBaseConfigSalesChannel\DocumentBaseConfigSalesChannelDefinition;
use Shopwell\Core\Checkout\DocumentV2\DocumentDefinition;
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
use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 *
 * @deprecated tag:v6.9.0 - Will be removed. Use `document.type_name` instead.
 */
#[Package('after-sales')]
class DocumentTypeDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'document_type';

    // @phpstan-ignore shopwell.internalMethod (Preserve the inherited public constructor contract.)
    public function __construct()
    {
        Feature::throwIfActive('v6.9.0.0', Feature::deprecatedClassMessage(self::class, 'v6.9.0.0'));
    }

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return DocumentTypeCollection::class;
    }

    public function getEntityClass(): string
    {
        return DocumentTypeEntity::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required())->setDescription('Unique identity of the document type.'),

            (new TranslatedField('name'))->addFlags(new ApiAware(), new SearchRanking(SearchRanking::MIDDLE_SEARCH_RANKING)),
            (new StringField('technical_name', 'technicalName'))->addFlags(new ApiAware(), new Required())->setDescription('Technical name of document type.'),
            (new TranslatedField('customFields'))->addFlags(new ApiAware()),

            (new TranslationsAssociationField(DocumentTypeTranslationDefinition::class, 'document_type_id'))->addFlags(new ApiAware(), new Required()),
            new OneToManyAssociationField('documents', DocumentDefinition::class, 'document_type_id'),
            (new OneToManyAssociationField('documentBaseConfigs', DocumentBaseConfigDefinition::class, 'document_type_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('documentBaseConfigSalesChannels', DocumentBaseConfigSalesChannelDefinition::class, 'document_type_id'))->addFlags(new CascadeDelete()),
        ]);
    }
}
