<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Media;

use Shopwell\Core\Checkout\DocumentV2\Aggregate\DocumentBaseConfig\DocumentBaseConfigDefinition;
use Shopwell\Core\Checkout\DocumentV2\Aggregate\DocumentFile\DocumentFileDefinition;
use Shopwell\Core\Checkout\DocumentV2\DocumentDefinition;
use Shopwell\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemDefinition;
use Shopwell\Core\Checkout\Order\Aggregate\OrderLineItemDownload\OrderLineItemDownloadDefinition;
use Shopwell\Core\Checkout\Payment\PaymentMethodDefinition;
use Shopwell\Core\Checkout\Shipping\ShippingMethodDefinition;
use Shopwell\Core\Content\Category\CategoryDefinition;
use Shopwell\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockDefinition;
use Shopwell\Core\Content\Cms\Aggregate\CmsSection\CmsSectionDefinition;
use Shopwell\Core\Content\Cms\CmsPageDefinition;
use Shopwell\Core\Content\MailTemplate\Aggregate\MailTemplateMedia\MailTemplateMediaDefinition;
use Shopwell\Core\Content\Media\Aggregate\MediaFolder\MediaFolderDefinition;
use Shopwell\Core\Content\Media\Aggregate\MediaTag\MediaTagDefinition;
use Shopwell\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailDefinition;
use Shopwell\Core\Content\Media\Aggregate\MediaTranslation\MediaTranslationDefinition;
use Shopwell\Core\Content\Product\Aggregate\ProductConfiguratorSetting\ProductConfiguratorSettingDefinition;
use Shopwell\Core\Content\Product\Aggregate\ProductDownload\ProductDownloadDefinition;
use Shopwell\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopwell\Core\Content\Product\Aggregate\ProductMedia\ProductMediaDefinition;
use Shopwell\Core\Content\Product\ProductDefinition;
use Shopwell\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionDefinition;
use Shopwell\Core\Framework\App\Aggregate\AppPaymentMethod\AppPaymentMethodDefinition;
use Shopwell\Core\Framework\App\Aggregate\AppShippingMethod\AppShippingMethodDefinition;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\BlobField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Computed;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Runtime;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\SetNullOnDelete;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Tag\TagDefinition;
use Shopwell\Core\System\User\UserDefinition;

/**
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class MediaDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'media';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return MediaCollection::class;
    }

    public function getEntityClass(): string
    {
        return MediaEntity::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    public function getHydratorClass(): string
    {
        return MediaHydrator::class;
    }

    public function getRestrictDeleteMetaFields(): FieldCollection
    {
        return $this->getFields()->filter(
            static fn (Field $field) => \in_array($field->getPropertyName(), ['id', 'fileName', 'fileExtension'], true)
        );
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required())->setDescription('Unique identity of the media.'),
            (new FkField('user_id', 'userId', UserDefinition::class))->setDescription('Unique identity of the user'),
            (new FkField('media_folder_id', 'mediaFolderId', MediaFolderDefinition::class))->setDescription('Unique identity of the media folder.'),
            (new StringField('mime_type', 'mimeType'))->addFlags(new ApiAware(), new SearchRanking(SearchRanking::LOW_SEARCH_RANKING))->setDescription('A string sent along with a file indicating the type of the file. For example: image/jpeg.'),
            (new StringField('file_extension', 'fileExtension'))->addFlags(new ApiAware())->setDescription('Type of file indication. For example: jpeg, png.'),
            (new DateTimeField('uploaded_at', 'uploadedAt'))->addFlags(new ApiAware(), new WriteProtected(Context::SYSTEM_SCOPE))->setDescription('Date and time at which media was added.'),
            (new LongTextField('file_name', 'fileName'))->addFlags(new ApiAware(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING))->setDescription('Name of the media file uploaded.'),
            (new IntField('file_size', 'fileSize'))->addFlags(new ApiAware(), new WriteProtected(Context::SYSTEM_SCOPE))->setDescription('Size of the file media file uploaded.'),
            (new BlobField('media_type', 'mediaTypeRaw'))->removeFlag(ApiAware::class)->addFlags(new WriteProtected(Context::SYSTEM_SCOPE)),
            (new JsonField('meta_data', 'metaData'))->addFlags(new ApiAware(), new WriteProtected(Context::SYSTEM_SCOPE))->setDescription('Details of the media file uploaded.'),
            (new JsonField('media_type', 'mediaType'))->addFlags(new WriteProtected(), new Runtime())->setDescription('Type or format of media content, such as images, videos, or audio files.'),
            (new JsonField('config', 'config'))->addFlags(new ApiAware()),
            (new TranslatedField('alt'))->addFlags(new ApiAware(), new SearchRanking(SearchRanking::MIDDLE_SEARCH_RANKING)),
            (new TranslatedField('title'))->addFlags(new ApiAware(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            (new StringField('url', 'url'))->addFlags(new ApiAware(), new Runtime(['path', 'private', 'updatedAt'])),
            (new StringField('path', 'path', 2048))->addFlags(new ApiAware()),
            (new BoolField('has_file', 'hasFile'))->addFlags(new ApiAware(), new Runtime()),
            (new BoolField('private', 'private'))->addFlags(new ApiAware())->setDescription('When `true`, the media display is kept private.'),
            (new TranslatedField('customFields'))->addFlags(new ApiAware()),
            (new BlobField('thumbnails_ro', 'thumbnailsRo'))->removeFlag(ApiAware::class)->addFlags(new Computed()),
            (new TranslationsAssociationField(MediaTranslationDefinition::class, 'media_id'))->addFlags(new ApiAware(), new Required()),
            (new ManyToManyAssociationField('tags', TagDefinition::class, MediaTagDefinition::class, 'media_id', 'tag_id'))->addFlags(new SearchRanking(SearchRanking::ASSOCIATION_SEARCH_RANKING)),
            (new OneToManyAssociationField('thumbnails', MediaThumbnailDefinition::class, 'media_id'))->addFlags(new ApiAware(), new CascadeDelete())->setDescription('Generated thumbnail images in various sizes'),
            // reverse side of the associations, not available in store-api
            new ManyToOneAssociationField('user', 'user_id', UserDefinition::class, 'id', false),
            (new OneToManyAssociationField('categories', CategoryDefinition::class, 'media_id', 'id'))->addFlags(new SetNullOnDelete()),
            (new OneToManyAssociationField('productManufacturers', ProductManufacturerDefinition::class, 'media_id', 'id'))->addFlags(new SetNullOnDelete()),
            (new OneToManyAssociationField('productMedia', ProductMediaDefinition::class, 'media_id', 'id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('productDownloads', ProductDownloadDefinition::class, 'media_id', 'id'))->addFlags(new RestrictDelete()),
            (new OneToManyAssociationField('orderLineItemDownloads', OrderLineItemDownloadDefinition::class, 'media_id', 'id'))->addFlags(new RestrictDelete()),
            (new OneToManyAssociationField('avatarUsers', UserDefinition::class, 'avatar_id'))->addFlags(new SetNullOnDelete()),
            new ManyToOneAssociationField('mediaFolder', 'media_folder_id', MediaFolderDefinition::class, 'id', false),
            (new OneToManyAssociationField('propertyGroupOptions', PropertyGroupOptionDefinition::class, 'media_id'))->addFlags(new SetNullOnDelete()),
            (new OneToManyAssociationField('mailTemplateMedia', MailTemplateMediaDefinition::class, 'media_id', 'id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('documentBaseConfigs', DocumentBaseConfigDefinition::class, 'logo_id', 'id'))->addFlags(new SetNullOnDelete()),
            (new OneToManyAssociationField('shippingMethods', ShippingMethodDefinition::class, 'media_id'))->addFlags(new SetNullOnDelete()),
            (new OneToManyAssociationField('paymentMethods', PaymentMethodDefinition::class, 'media_id', 'id'))->addFlags(new SetNullOnDelete()),
            (new OneToManyAssociationField('productConfiguratorSettings', ProductConfiguratorSettingDefinition::class, 'media_id'))->addFlags(new SetNullOnDelete()),
            (new OneToManyAssociationField('productOpenGraphImages', ProductDefinition::class, 'open_graph_media_id', 'id'))->addFlags(new SetNullOnDelete()),
            (new OneToManyAssociationField('orderLineItems', OrderLineItemDefinition::class, 'cover_id'))->addFlags(new SetNullOnDelete()),
            (new OneToManyAssociationField('cmsBlocks', CmsBlockDefinition::class, 'background_media_id'))->addFlags(new RestrictDelete()),
            (new OneToManyAssociationField('cmsSections', CmsSectionDefinition::class, 'background_media_id'))->addFlags(new RestrictDelete()),
            (new OneToManyAssociationField('cmsPages', CmsPageDefinition::class, 'preview_media_id'))->addFlags(new RestrictDelete()),
            (new OneToManyAssociationField('documents', DocumentDefinition::class, 'document_media_file_id'))->addFlags(new RestrictDelete()),
            (new OneToManyAssociationField('a11yDocuments', DocumentDefinition::class, 'document_a11y_media_file_id'))->addFlags(new RestrictDelete()),
            (new OneToManyAssociationField('appPaymentMethods', AppPaymentMethodDefinition::class, 'original_media_id', 'id'))->addFlags(new SetNullOnDelete()),
            (new OneToManyAssociationField('appShippingMethods', AppShippingMethodDefinition::class, 'original_media_id', 'id'))->addFlags(new SetNullOnDelete()),
            (new OneToOneAssociationField('documentFile', 'id', 'media_id', DocumentFileDefinition::class, false))->addFlags(new RestrictDelete()),
            (new StringField('file_hash', 'fileHash'))->addFlags(new Computed()),
        ]);
    }
}
