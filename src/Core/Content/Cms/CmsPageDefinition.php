<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Cms;

use Shopwell\Core\Content\Category\CategoryDefinition;
use Shopwell\Core\Content\Cms\Aggregate\CmsPageTranslation\CmsPageTranslationDefinition;
use Shopwell\Core\Content\Cms\Aggregate\CmsSection\CmsSectionDefinition;
use Shopwell\Core\Content\LandingPage\LandingPageDefinition;
use Shopwell\Core\Content\Media\MediaDefinition;
use Shopwell\Core\Content\Product\ProductDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\LockedField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelDefinition;

/**
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class CmsPageDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'cms_page';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return CmsPageEntity::class;
    }

    public function getCollectionClass(): string
    {
        return CmsPageCollection::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required())->setDescription('Unique identity of CMS page.'),
            (new VersionField())->addFlags(new ApiAware()),
            (new TranslatedField('name'))->addFlags(new ApiAware()),
            (new StringField('type', 'type'))->addFlags(new ApiAware(), new Required())->setDescription('CMS page types can be `landingpage`, `page`, `product_list`, `product_detail`.'),
            (new StringField('entity', 'entity'))->addFlags(new ApiAware())->setDescription('This field will be implemented in the future.'),
            (new StringField('css_class', 'cssClass'))->addFlags(new ApiAware())->setDescription('One or more CSS classes added and separated by spaces.'),
            (new JsonField('config', 'config', [
                (new StringField('background_color', 'backgroundColor'))->addFlags(new ApiAware()),
            ]))->addFlags(new ApiAware())->setDescription('Background color of the CMS page.'),
            (new FkField('preview_media_id', 'previewMediaId', MediaDefinition::class))->addFlags(new ApiAware())->setDescription('Unique identity of media to be previewed.'),
            (new TranslatedField('customFields'))->addFlags(new ApiAware()),
            new LockedField(),

            (new OneToManyAssociationField('sections', CmsSectionDefinition::class, 'cms_page_id'))->addFlags(new ApiAware(), new CascadeDelete())->setDescription('Content sections within the CMS page (layout blocks containing slots)'),
            (new TranslationsAssociationField(CmsPageTranslationDefinition::class, 'cms_page_id'))->addFlags(new ApiAware()),
            (new ManyToOneAssociationField('previewMedia', 'preview_media_id', MediaDefinition::class, 'id', false))->addFlags(new ApiAware())->setDescription('Preview image for the CMS page in admin panel and page selection'),

            (new OneToManyAssociationField('categories', CategoryDefinition::class, 'cms_page_id'))->addFlags(new RestrictDelete()),
            (new OneToManyAssociationField('landingPages', LandingPageDefinition::class, 'cms_page_id'))->addFlags(new ApiAware(), new RestrictDelete())->setDescription('Landing pages using this CMS layout'),
            (new OneToManyAssociationField('homeSalesChannels', SalesChannelDefinition::class, 'home_cms_page_id'))->addFlags(new RestrictDelete()),
            (new OneToManyAssociationField('products', ProductDefinition::class, 'cms_page_id'))->addFlags(new RestrictDelete()),
        ]);
    }
}
