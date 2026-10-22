<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Blog\Aggregate\BlogAuthor;

use Shopwell\Core\Content\Blog\BlogEntity;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Field;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\FieldType;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\ForeignKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\OnDelete;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\OneToMany;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\SearchRanking;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Translations;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity as EntityStruct;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\ArrayEntity;

/**
 * The person an entry is signed with - a shop employee, a guest author or an external columnist.
 *
 * An author is deliberately not a customer and not a user: a shop has to be able to sign an article
 * with somebody who has no login at all, and a leaving employee must not take the byline away. The
 * e-mail address is optional and never leaves the administration, it exists so an editor can reach
 * the person behind a byline.
 *
 * An author is never deleted when an entry is written by them: the foreign key of the entry is set to
 * null instead, and an entry without an author is rendered without byline.
 *
 * @codeCoverageIgnore
 */
#[Package('discovery')]
#[Entity('blog_author', since: '6.7.18.0', collectionClass: BlogAuthorCollection::class)]
class BlogAuthorEntity extends EntityStruct
{
    /**
     * The schema level extension point, mirroring the one on the entry.
     *
     * The author record is deliberately small - a name, a biography, a portrait - because most of what
     * a shop wants to say about a person differs per installation. "Job title", "social handle",
     * "external profile id" are exactly the kind of field that belongs in a plugin's custom fieldset
     * and not in this class.
     */
    use EntityCustomFieldsTrait;

    final public const ENTITY_NAME = 'blog_author';

    #[PrimaryKey]
    #[Field(type: FieldType::UUID, api: ['admin-api' => true, 'store-api' => true])]
    public string $id;

    /**
     * The display name of the author, translated so a byline can be localised.
     */
    #[Required]
    #[Field(type: FieldType::STRING, translated: true, api: ['admin-api' => true, 'store-api' => true], maxLength: 255)]
    #[SearchRanking(SearchRanking::MIDDLE_SEARCH_RANKING)]
    public string $name;

    /**
     * The short biography shown beneath the entry, translated as well.
     */
    #[Field(type: FieldType::TEXT, translated: true, api: ['admin-api' => true, 'store-api' => true])]
    public ?string $description = null;

    /**
     * How an editor reaches the author. Administration only - publishing the address of a guest
     * author on the storefront would hand a spam bot a free list.
     */
    #[Field(type: FieldType::EMAIL, api: ['admin-api' => true, 'store-api' => false])]
    public ?string $email = null;

    /**
     * The portrait next to the byline. Only referenced, so deleting the image leaves the author
     * intact and the byline simply renders without a picture.
     *
     * Only the id is modelled, without an association to the media entity: the dal insists on a
     * reverse one-to-many association on every many-to-one, and `media` belongs to another domain
     * that this one must not grow fields on.
     */
    #[ForeignKey(entity: 'media', api: ['admin-api' => true, 'store-api' => true])]
    public ?string $mediaId = null;

    /**
     * A switched-off author is not offered for new entries anymore. The entries that already carry
     * the byline keep it - the name of the person who wrote an article does not become untrue.
     */
    #[Field(type: FieldType::BOOL, api: ['admin-api' => true, 'store-api' => true])]
    public bool $active = true;

    /**
     * The translated columns of the author, one row per language.
     *
     * @var array<string, ArrayEntity>|null
     */
    #[Translations]
    public ?array $translations = null;

    /**
     * The entries this author wrote. Deleting the author only clears their byline.
     *
     * @var array<string, BlogEntity>|null
     */
    #[OneToMany(entity: 'blog', ref: 'author_id', onDelete: OnDelete::SET_NULL, api: ['admin-api' => true, 'store-api' => true])]
    public ?array $entries = null;
}
