<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Blog;

use Shopwell\Core\Content\Blog\Aggregate\BlogAuthor\BlogAuthorEntity;
use Shopwell\Core\Content\Blog\Aggregate\BlogCategoryAssignment\BlogCategoryAssignmentEntity;
use Shopwell\Core\Content\Blog\Aggregate\BlogComment\BlogCommentEntity;
use Shopwell\Core\Content\Blog\Aggregate\BlogVideo\BlogVideoEntity;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Field;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\FieldType;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\ForeignKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\ManyToOne;
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
 * One editorial page of the shop: an article, a video page or a page built out of articles.
 *
 * The entry is the shell only - everything a visitor reads lives in the translation, because a shop
 * addresses its readers in more than one language and the same page is written once per language.
 * What stays untranslated is the editorial decision: `type`, `publishedAt` and the references are the
 * same in every language.
 *
 * There is no `active` flag. A blog is not a shop section a merchant switches on and off; it is
 * content, and content is governed by its date. An entry that should not be read yet carries a
 * `publishedAt` in the future, which the visibility guard already understands - a second switch would
 * only create the state where an entry is active but undated, or dated but inactive, and every
 * template would have to decide what that means.
 *
 * `type` decides how the page is rendered. It is a plain string instead of a state so a plugin can
 * bring its own type together with a {@see \Shopwell\Core\Content\Blog\Service\BlogTypeHandlerInterface}
 * implementation, without a schema change and without touching the core.
 *
 * @codeCoverageIgnore
 */
#[Package('discovery')]
#[Entity('blog', since: '6.7.18.0', collectionClass: BlogCollection::class)]
class BlogEntity extends EntityStruct
{
    /**
     * Brings `customFields` as a schema level extension point.
     *
     * Without it a plugin that wants to store "reading level" or "campaign" on an entry has only one
     * option left: change this class. The column is the contract every other entity of the system
     * offers, so the blog offers it too - and, exactly like the other entities, only on the shell.
     * The translation table stays free of it: a key stored on two levels at once would leave every
     * writer guessing which one wins.
     */
    use EntityCustomFieldsTrait;

    final public const ENTITY_NAME = 'blog';

    /**
     * A written article. The default, so an entry created without an explicit type is an article.
     */
    final public const TYPE_POST = 'post';

    /**
     * A page whose main content is one video, rendered by the video detail template.
     */
    final public const TYPE_VIDEO = 'video';

    /**
     * Every type the core knows. A plugin may add more, which is why the field is not an enum.
     */
    final public const TYPE_LIST = [self::TYPE_POST, self::TYPE_VIDEO];

    #[PrimaryKey]
    #[Field(type: FieldType::UUID, api: ['admin-api' => true, 'store-api' => true])]
    public string $id;

    /**
     * How the page is rendered, one of the TYPE_* constants. Not immutable on purpose: a page may
     * start as an article and be turned into a video page without being recreated, and the id has to
     * survive that because the slug and the SEO url point at it.
     */
    #[Required]
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => true], maxLength: 32)]
    public string $type = self::TYPE_POST;

    /**
     * The moment the entry goes live. Null means "as soon as it is saved", which is what an editor
     * expects when the page is written and saved in one step.
     *
     * A value in the future is a scheduled entry: the row exists, the merchant sees it in the
     * administration, but the storefront hides it until the moment has come. The decision is made by
     * {@see \Shopwell\Core\Content\Blog\Service\BlogVisibilityGuard}, never inline.
     */
    #[Field(type: FieldType::DATETIME, api: ['admin-api' => true, 'store-api' => true])]
    public ?\DateTimeInterface $publishedAt = null;

    /**
     * The headline of the entry. Translated, so it lives in `blog_translation` only.
     */
    #[Required]
    #[Field(type: FieldType::STRING, translated: true, api: ['admin-api' => true, 'store-api' => true], maxLength: 255)]
    #[SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)]
    public string $title;

    /**
     * The url segment of the entry, unique per language. It is translated because a Chinese and a
     * German page want their own readable url, and it stays the source of the SEO url the storefront
     * router resolves.
     */
    #[Required]
    #[Field(type: FieldType::STRING, translated: true, api: ['admin-api' => true, 'store-api' => true], maxLength: 255)]
    public string $slug;

    /**
     * The teaser shown in the listing, on the landing page and in the social preview.
     */
    #[Field(type: FieldType::TEXT, translated: true, api: ['admin-api' => true, 'store-api' => true])]
    public ?string $teaser = null;

    /**
     * The body of the entry as rendered by the editor. It may contain html, which is why it is a
     * text column and not a string one.
     */
    #[Field(type: FieldType::TEXT, translated: true, api: ['admin-api' => true, 'store-api' => true])]
    public ?string $content = null;

    /**
     * The `<title>` of the page. Null falls back to the title, decided by the storefront presenter
     * and not here, so the fallback can follow the SEO configuration of the sales channel.
     */
    #[Field(type: FieldType::STRING, translated: true, api: ['admin-api' => true, 'store-api' => true], maxLength: 255)]
    public ?string $metaTitle = null;

    #[Field(type: FieldType::STRING, translated: true, api: ['admin-api' => true, 'store-api' => true], maxLength: 512)]
    public ?string $metaDescription = null;

    /**
     * The `<meta name="keywords">` of the page, translated like the rest of the seo block.
     *
     * Search engines stopped weighting the tag years ago, but the field is part of the seo triple every
     * other entity of the system carries (`metaTitle`, `metaDescription`, `keywords`), and an editor
     * migrating an article from another system expects to find the slot where it was.
     */
    #[Field(type: FieldType::STRING, translated: true, api: ['admin-api' => true, 'store-api' => true], maxLength: 255)]
    public ?string $keywords = null;

    /**
     * Who wrote the entry. Null is allowed: an article may outlive its author, and deleting an author
     * must not delete the articles that were written by them.
     */
    #[ForeignKey(entity: 'blog_author', api: ['admin-api' => true, 'store-api' => true])]
    public ?string $authorId = null;

    /**
     * The image used in the listing and as the fallback for the detail page header.
     *
     * Only the id is modelled, without an association to the media entity: the dal insists on a
     * reverse one-to-many association on every many-to-one, and `media` belongs to another domain
     * that this one must not grow fields on. The storefront resolves the image from the media
     * repository by this id.
     */
    #[ForeignKey(entity: 'media', api: ['admin-api' => true, 'store-api' => true])]
    public ?string $coverMediaId = null;

    /**
     * The layout of the detail page. An entry without a cms page falls back to the twig template of
     * its type, which is what an installation without the administration editor expects; a merchant
     * that builds a layout wins.
     */
    #[ForeignKey(entity: 'cms_page', api: ['admin-api' => true, 'store-api' => true])]
    public ?string $cmsPageId = null;

    /**
     * The layout is version aware, so a reference into it always spans id and version. Without the
     * version the foreign key can not be created at all - `cms_page` is keyed by (id, version_id) and
     * MySQL 8.4 rejects a foreign key that references only part of it.
     */
    #[ReferenceVersion(entity: 'cms_page')]
    public ?string $cmsPageVersionId = null;

    /**
     * The translated columns of the entry, one row per language.
     *
     * Declaring the association is mandatory: without it the schema builder never learns that the
     * translated fields above live in `blog_translation`, and the whole schema build fails.
     *
     * @var array<string, ArrayEntity>|null
     */
    #[Translations]
    public ?array $translations = null;

    #[ManyToOne(entity: 'blog_author', onDelete: OnDelete::SET_NULL, api: ['admin-api' => true, 'store-api' => true])]
    public ?BlogAuthorEntity $author = null;

    /**
     * The categories the entry is filed under. The assignment rows only exist together with it.
     *
     * @var array<string, BlogCategoryAssignmentEntity>|null
     */
    #[OneToMany(entity: 'blog_category_assignment', ref: 'blog_id', onDelete: OnDelete::CASCADE, api: ['admin-api' => true, 'store-api' => true])]
    public ?array $categories = null;

    /**
     * The videos of the entry. A video page has exactly one - the unique key on `blog_id` keeps it
     * that way - but the association is a list because the dal has no one to one relation between
     * attribute based entities, and a list models the cardinality just as well.
     *
     * @var array<string, BlogVideoEntity>|null
     */
    #[OneToMany(entity: 'blog_video', ref: 'blog_id', onDelete: OnDelete::CASCADE, api: ['admin-api' => true, 'store-api' => true])]
    public ?array $videos = null;

    /**
     * The comments of the entry, including the ones that are not approved yet. The storefront filters
     * them itself - an association that silently drops rows would make a moderator unable to see what
     * is waiting.
     *
     * @var array<string, BlogCommentEntity>|null
     */
    #[OneToMany(entity: 'blog_comment', ref: 'blog_id', onDelete: OnDelete::CASCADE, api: ['admin-api' => true, 'store-api' => true])]
    public ?array $comments = null;
}
