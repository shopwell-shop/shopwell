<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Blog\Aggregate\BlogCategory;

use Shopwell\Core\Content\Blog\Aggregate\BlogCategoryAssignment\BlogCategoryAssignmentEntity;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Field;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\FieldType;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\ForeignKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\OnDelete;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\OneToMany;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\ReferenceVersion;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\SearchRanking;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Translations;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity as EntityStruct;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\ArrayEntity;

/**
 * A thematic bucket an entry is filed under, e.g. "Guides" or "Company news".
 *
 * The category is what a listing page of the blog navigates by, so it carries a slug of its own next
 * to the name. Both are translated: a shop that writes in two languages wants two readable listing
 * urls, and the slug is unique per language instead of globally, so the same segment may be used in
 * each language without a collision.
 *
 * A category is flat on purpose. A tree would need a nested set that has to be reindexed on every
 * write, and a blog with more than two levels is a content problem, not a data problem.
 *
 * @codeCoverageIgnore
 */
#[Package('discovery')]
#[Entity('blog_category', since: '6.7.18.0', collectionClass: BlogCategoryCollection::class)]
class BlogCategoryEntity extends EntityStruct
{
    /**
     * The schema level extension point, mirroring the one on the entry.
     *
     * A plugin that wants to hang a colour, a teaser image or an external mapping id on a category
     * should not have to touch this class, and a merchant that files twenty categories should not have
     * to wait for a core release to do it.
     */
    use EntityCustomFieldsTrait;

    final public const ENTITY_NAME = 'blog_category';

    #[PrimaryKey]
    #[Field(type: FieldType::UUID, api: ['admin-api' => true, 'store-api' => true])]
    public string $id;

    /**
     * The label of the category, shown in the navigation and on the listing page. Translated, so it
     * lives in `blog_category_translation` only.
     */
    #[Required]
    #[Field(type: FieldType::STRING, translated: true, api: ['admin-api' => true, 'store-api' => true], maxLength: 255)]
    #[SearchRanking(SearchRanking::MIDDLE_SEARCH_RANKING)]
    public string $name;

    /**
     * The url segment of the category, unique per language.
     */
    #[Required]
    #[Field(type: FieldType::STRING, translated: true, api: ['admin-api' => true, 'store-api' => true], maxLength: 255)]
    public string $slug;

    /**
     * The introduction shown above the listing, null when the category needs none.
     */
    #[Field(type: FieldType::TEXT, translated: true, api: ['admin-api' => true, 'store-api' => true])]
    public ?string $description = null;

    /**
     * The `<title>` of the listing page.
     *
     * A category has a url of its own - `frontend.blog.category` resolves it - so it needs a seo block
     * of its own as well. Without these three fields the only title a listing page could render is the
     * category name, which is what a merchant puts in the navigation, not what a search result should
     * say. Null falls back to the name, decided by the storefront and not here.
     */
    #[Field(type: FieldType::STRING, translated: true, api: ['admin-api' => true, 'store-api' => true], maxLength: 255)]
    public ?string $metaTitle = null;

    #[Field(type: FieldType::STRING, translated: true, api: ['admin-api' => true, 'store-api' => true], maxLength: 512)]
    public ?string $metaDescription = null;

    #[Field(type: FieldType::STRING, translated: true, api: ['admin-api' => true, 'store-api' => true], maxLength: 255)]
    public ?string $keywords = null;

    /**
     * A switched-off category is not offered in the navigation anymore. The entries that are filed
     * under it stay reachable through their own url, they only lose the listing entry.
     */
    #[Field(type: FieldType::BOOL, api: ['admin-api' => true, 'store-api' => true])]
    public bool $active = true;

    /**
     * The layout of the category's listing page. A merchant that builds a listing in the administration
     * gets exactly that page; an empty value falls back to the twig listing.
     *
     * The layout of the listing belongs here and not to a global configuration: two categories of the
     * same blog may want different densities, and a shop wide switch would force one on both.
     */
    #[ForeignKey(entity: 'cms_page', api: ['admin-api' => true, 'store-api' => true])]
    public ?string $cmsPageId = null;

    /**
     * The layout is version aware, so a reference into it always spans id and version - `cms_page` is
     * keyed by (id, version_id) and MySQL 8.4 rejects a foreign key that references only part of it.
     */
    #[ReferenceVersion(entity: 'cms_page')]
    public ?string $cmsPageVersionId = null;

    /**
     * The translated columns of the category, one row per language.
     *
     * @var array<string, ArrayEntity>|null
     */
    #[Translations]
    public ?array $translations = null;

    /**
     * The assignments of entries into this category. Deleting the category takes the assignments with
     * it and leaves the entries untouched - an entry without a category is still an entry.
     *
     * @var array<string, BlogCategoryAssignmentEntity>|null
     */
    #[OneToMany(entity: 'blog_category_assignment', ref: 'blog_category_id', onDelete: OnDelete::CASCADE, api: ['admin-api' => true, 'store-api' => true])]
    public ?array $assignments = null;
}
