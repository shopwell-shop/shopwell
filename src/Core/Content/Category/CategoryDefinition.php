<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Category;

use Shopwell\Core\Content\Category\Aggregate\CategoryTag\CategoryTagDefinition;
use Shopwell\Core\Content\Category\Aggregate\CategoryTranslation\CategoryTranslationDefinition;
use Shopwell\Core\Content\Cms\CmsPageDefinition;
use Shopwell\Core\Content\Media\MediaDefinition;
use Shopwell\Core\Content\Product\Aggregate\ProductCategory\ProductCategoryDefinition;
use Shopwell\Core\Content\Product\Aggregate\ProductCategoryTree\ProductCategoryTreeDefinition;
use Shopwell\Core\Content\Product\ProductDefinition;
use Shopwell\Core\Content\ProductStream\ProductStreamDefinition;
use Shopwell\Core\Content\Seo\MainCategory\MainCategoryDefinition;
use Shopwell\Core\Content\Seo\SeoUrl\SeoUrlDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\AutoIncrementField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ChildCountField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ChildrenAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Choice;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Deprecated;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\ReverseInherited;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Runtime;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ParentAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ParentFkField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\TreeLevelField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\TreePathField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\CustomEntity\CustomEntityDefinition;
use Shopwell\Core\System\SalesChannel\SalesChannelDefinition;
use Shopwell\Core\System\Tag\TagDefinition;

#[Package('discovery')]
class CategoryDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'category';

    final public const TYPE_PAGE = 'page';

    final public const TYPE_LINK = 'link';

    final public const TYPE_FOLDER = 'folder';

    final public const LINK_TYPE_EXTERNAL = 'external';

    final public const LINK_TYPE_CATEGORY = 'category';

    final public const LINK_TYPE_PRODUCT = 'product';

    final public const LINK_TYPE_LANDING_PAGE = 'landing_page';

    final public const PRODUCT_ASSIGNMENT_TYPE_PRODUCT = 'product';

    final public const PRODUCT_ASSIGNMENT_TYPE_PRODUCT_STREAM = 'product_stream';

    final public const CONFIG_KEY_DEFAULT_CMS_PAGE_CATEGORY = 'core.cms.default_category_cms_page';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return CategoryCollection::class;
    }

    public function getEntityClass(): string
    {
        return CategoryEntity::class;
    }

    public function getDefaults(): array
    {
        return [
            'displayNestedProducts' => true,
            'type' => self::TYPE_PAGE,
            'productAssignmentType' => self::PRODUCT_ASSIGNMENT_TYPE_PRODUCT,
        ];
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    public function getHydratorClass(): string
    {
        return CategoryHydrator::class;
    }

    protected function defineFields(): FieldCollection
    {
        $fields = new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required())->setDescription('Unique identity of category.'),
            (new VersionField())->addFlags(new ApiAware()),

            (new ParentFkField(self::class))->addFlags(new ApiAware()),
            (new ReferenceVersionField(self::class, 'parent_version_id'))->addFlags(new ApiAware(), new Required()),

            (new FkField('after_category_id', 'afterCategoryId', self::class))->addFlags(new ApiAware())->setDescription('Unique identity of the category under which the new category is to be created.'),
            (new ReferenceVersionField(self::class, 'after_category_version_id'))->addFlags(new ApiAware(), new Required()),

            (new FkField('media_id', 'mediaId', MediaDefinition::class))->addFlags(new ApiAware())->setDescription('Unique identity of media added to identify category.'),

            (new BoolField('display_nested_products', 'displayNestedProducts'))->addFlags(new ApiAware(), new Required())->setDescription('Shows nested categories on a product category page.'),
            new AutoIncrementField(),

            (new TranslatedField('breadcrumb'))->addFlags(new ApiAware(), new WriteProtected()),
            (new TreeLevelField('level', 'level'))->addFlags(new ApiAware())->setDescription('An integer value that denotes the level of nesting of a particular category located in an hierarchical category tree.'),
            (new TreePathField('path', 'path'))->addFlags(new ApiAware())->setDescription('A relative URL to the category.'),
            (new ChildCountField())->addFlags(new ApiAware()),

            (new StringField('type', 'type'))->addFlags(new ApiAware(), new Required(), new Choice([
                self::TYPE_PAGE,
                self::TYPE_LINK,
                self::TYPE_FOLDER,
            ]))->setDescription('Type of categories like `page`, `folder`, `link`.'),
            (new StringField('product_assignment_type', 'productAssignmentType'))->addFlags(new ApiAware(), new Required())->setDescription('Type of product assignment: Dynamic product group as or `product_stream` or Manual assignment as `product`.'),
            (new BoolField('visible', 'visible'))->addFlags(new ApiAware())->setDescription('Displays categories on category page when true.'),
            (new BoolField('active', 'active'))->addFlags(new ApiAware())->setDescription('When boolean value is `true`, the category is listed for selection.'),

            (new IntField('visible_child_count', 'visibleChildCount'))->addFlags(new Runtime(), new ApiAware()),

            (new TranslatedField('name'))->addFlags(new ApiAware(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            (new TranslatedField('customFields'))->addFlags(new ApiAware()),
            new TranslatedField('slotConfig'),
            (new TranslatedField('linkType'))->addFlags(new ApiAware()),
            (new TranslatedField('internalLink'))->addFlags(new ApiAware()),
            (new TranslatedField('externalLink'))->addFlags(new ApiAware()),
            (new TranslatedField('linkNewTab'))->addFlags(new ApiAware()),
            (new TranslatedField('description'))->addFlags(new ApiAware()),
            (new TranslatedField('metaTitle'))->addFlags(new ApiAware()),
            (new TranslatedField('metaDescription'))->addFlags(new ApiAware()),
            (new TranslatedField('keywords'))->addFlags(new ApiAware()),

            (new ParentAssociationField(self::class, 'id'))->addFlags(new ApiAware())->setDescription('Unique identity of category.'),
            (new ChildrenAssociationField(self::class))->addFlags(new ApiAware())->setDescription('Child categories within this category for hierarchical navigation'),

            (new ManyToOneAssociationField('media', 'media_id', MediaDefinition::class, 'id', false))->addFlags(new ApiAware())->setDescription('Category image or banner'),
            (new TranslationsAssociationField(CategoryTranslationDefinition::class, 'category_id'))->addFlags(new ApiAware(), new Required()),
            (new ManyToManyAssociationField('products', ProductDefinition::class, ProductCategoryDefinition::class, 'category_id', 'product_id'))->addFlags(new CascadeDelete(), new ReverseInherited('categories')),
            (new ManyToManyAssociationField('nestedProducts', ProductDefinition::class, ProductCategoryTreeDefinition::class, 'category_id', 'product_id'))->addFlags(new CascadeDelete(), new WriteProtected()),
            (new ManyToManyAssociationField('tags', TagDefinition::class, CategoryTagDefinition::class, 'category_id', 'tag_id'))->addFlags(new ApiAware(), new CascadeDelete())->setDescription('Tags for organizing and filtering categories'),

            (new FkField('cms_page_id', 'cmsPageId', CmsPageDefinition::class))->addFlags(new ApiAware())->setDescription('Unique identity of CMS page.'),
            (new ReferenceVersionField(CmsPageDefinition::class))->addFlags(new Required(), new ApiAware()),
            (new ManyToOneAssociationField('cmsPage', 'cms_page_id', CmsPageDefinition::class, 'id', false))->addFlags(new ApiAware())->setDescription('CMS page layout for the category'),
            (new FkField('product_stream_id', 'productStreamId', ProductStreamDefinition::class))->setDescription('Unique identity of product stream.'),
            new ManyToOneAssociationField('productStream', 'product_stream_id', ProductStreamDefinition::class, 'id', false),

            // custom entity specific fields
            (new FkField('custom_entity_type_id', 'customEntityTypeId', CustomEntityDefinition::class, 'id'))->addFlags(new ApiAware()),

            // Reverse Associations not available in store-api->setDescription('Unique identity of custom entity type.')
            new OneToManyAssociationField('navigationSalesChannels', SalesChannelDefinition::class, 'navigation_category_id'),
            new OneToManyAssociationField('footerSalesChannels', SalesChannelDefinition::class, 'footer_category_id'),
            new OneToManyAssociationField('serviceSalesChannels', SalesChannelDefinition::class, 'service_category_id'),
            (new OneToManyAssociationField('mainCategories', MainCategoryDefinition::class, 'category_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('seoUrls', SeoUrlDefinition::class, 'foreign_key'))->addFlags(new ApiAware())->setDescription('SEO-friendly URLs for the category across different sales channels'),
        ]);

        if (!Feature::isActive('v6.8.0.0')) {
            $fields->add(
                (new BoolField('cms_page_id_switched', 'cmsPageIdSwitched'))->addFlags(new Runtime(), new ApiAware(), new Deprecated('v6.7.9.0', 'v6.8.0.0')),
            );
        }

        return $fields;
    }
}
