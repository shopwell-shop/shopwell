<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Media\Aggregate\MediaThumbnailSize;

use Shopwell\Core\Content\Media\Aggregate\MediaFolderConfiguration\MediaFolderConfigurationDefinition;
use Shopwell\Core\Content\Media\Aggregate\MediaFolderConfigurationMediaThumbnailSize\MediaFolderConfigurationMediaThumbnailSizeDefinition;
use Shopwell\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class MediaThumbnailSizeDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'media_thumbnail_size';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return MediaThumbnailSizeCollection::class;
    }

    public function getEntityClass(): string
    {
        return MediaThumbnailSizeEntity::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required())->setDescription('Unique identity of media thumbnail size defined.'),
            (new IntField('width', 'width', 1))->addFlags(new ApiAware(), new Required())->setDescription('Width of the thumbnail.'),
            (new IntField('height', 'height', 1))->addFlags(new ApiAware(), new Required())->setDescription('Height of the thumbnail.'),
            new ManyToManyAssociationField('mediaFolderConfigurations', MediaFolderConfigurationDefinition::class, MediaFolderConfigurationMediaThumbnailSizeDefinition::class, 'media_thumbnail_size_id', 'media_folder_configuration_id'),
            new OneToManyAssociationField('mediaThumbnails', MediaThumbnailDefinition::class, 'media_thumbnail_size_id', 'id'),
            (new CustomFields())->addFlags(new ApiAware())->setDescription('Additional fields that offer a possibility to add own fields for the different program-areas.'),
        ]);
    }
}
