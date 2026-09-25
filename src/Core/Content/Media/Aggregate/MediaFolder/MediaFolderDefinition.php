<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Media\Aggregate\MediaFolder;

use Shopwell\Core\Content\Media\Aggregate\MediaDefaultFolder\MediaDefaultFolderDefinition;
use Shopwell\Core\Content\Media\Aggregate\MediaFolderConfiguration\MediaFolderConfigurationDefinition;
use Shopwell\Core\Content\Media\MediaDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ChildCountField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ChildrenAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\SetNullOnDelete;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ParentAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ParentFkField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\TreePathField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class MediaFolderDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'media_folder';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return MediaFolderCollection::class;
    }

    public function getEntityClass(): string
    {
        return MediaFolderEntity::class;
    }

    public function getDefaults(): array
    {
        return ['useParentConfiguration' => true];
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required())->setDescription('Unique identity of media folder.'),
            (new BoolField('use_parent_configuration', 'useParentConfiguration'))->setDescription('When boolean value is `true`, the folder inherits the configuration settings of its parent folder.'),
            (new FkField('media_folder_configuration_id', 'configurationId', MediaFolderConfigurationDefinition::class))->addFlags(new Required())->setDescription('Unique identity of configuration.'),
            (new FkField('default_folder_id', 'defaultFolderId', MediaDefaultFolderDefinition::class))->setDescription('Unique identity of default folder.'),
            new ParentFkField(self::class),
            (new ParentAssociationField(self::class, 'id'))->setDescription('Unique identity of media folder.'),
            new ChildrenAssociationField(self::class),
            new ChildCountField(),
            (new TreePathField('path', 'path'))->setDescription('A relative URL to the media folder.'),
            (new OneToManyAssociationField('media', MediaDefinition::class, 'media_folder_id'))->addFlags(new SetNullOnDelete()),
            new OneToOneAssociationField('defaultFolder', 'default_folder_id', 'id', MediaDefaultFolderDefinition::class, false),
            new ManyToOneAssociationField('configuration', 'media_folder_configuration_id', MediaFolderConfigurationDefinition::class, 'id', false),
            (new StringField('name', 'name'))->addFlags(new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING), new Required())->setDescription('Name of media folder.'),
            (new CustomFields())->setDescription('Additional fields that offer a possibility to add own fields for the different program-areas.'),
        ]);
    }
}
