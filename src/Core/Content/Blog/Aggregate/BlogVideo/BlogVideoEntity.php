<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Blog\Aggregate\BlogVideo;

use Shopwell\Core\Content\Blog\BlogEntity;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Field;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\FieldType;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\ForeignKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\ManyToOne;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity as EntityStruct;
use Shopwell\Core\Framework\Log\Package;

/**
 * The video of a video entry: a reference to where the media is hosted plus the poster frame.
 *
 * The video itself is never stored, only its address. A shop that hosts its videos elsewhere keeps
 * the bandwidth bill there, and a re-encoded file only changes the address in this row instead of the
 * whole entry. `provider` names the platform the address belongs to, so the storefront can pick the
 * right player (and the right privacy settings) without parsing the url.
 *
 * An entry has exactly one video, modelled as a many to one from this side with a unique key over
 * `blog_id`: the dal has no one to one association between attribute based entities, and the unique
 * key states the cardinality just as precisely.
 *
 * @codeCoverageIgnore
 */
#[Package('discovery')]
#[Entity('blog_video', since: '6.7.18.0', collectionClass: BlogVideoCollection::class)]
class BlogVideoEntity extends EntityStruct
{
    final public const ENTITY_NAME = 'blog_video';

    final public const PROVIDER_YOUTUBE = 'youtube';
    final public const PROVIDER_VIMEO = 'vimeo';
    final public const PROVIDER_SELF_HOSTED = 'self-hosted';

    #[PrimaryKey]
    #[Field(type: FieldType::UUID, api: ['admin-api' => true, 'store-api' => true])]
    public string $id;

    #[Required]
    #[ForeignKey(entity: 'blog', api: ['admin-api' => true, 'store-api' => true])]
    public string $blogId;

    /**
     * Where the video is served from. Longer than a plain string field on purpose: a self hosted
     * address with a signed query string easily passes the 255 characters of a normal string.
     */
    #[Required]
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => true], maxLength: 2048)]
    public string $videoUrl;

    /**
     * The still image shown before the player is started. Only referenced, deleting it leaves the
     * video playable without a poster.
     *
     * Only the id is modelled, without an association to the media entity: the dal insists on a
     * reverse one-to-many association on every many-to-one, and `media` belongs to another domain
     * that this one must not grow fields on.
     */
    #[ForeignKey(entity: 'media', api: ['admin-api' => true, 'store-api' => true])]
    public ?string $posterMediaId = null;

    /**
     * The length of the video in seconds, used by the structured data and the listing. Null when the
     * platform does not tell it, which is common for live recordings.
     */
    #[Field(type: FieldType::INT, api: ['admin-api' => true, 'store-api' => true])]
    public ?int $durationSeconds = null;

    /**
     * The platform that hosts the video, one of the PROVIDER_* constants or a value a plugin owns.
     */
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => true], maxLength: 64)]
    public ?string $provider = null;

    /**
     * The video belongs to its entry, the cascade is declared on the reverse side in BlogEntity.
     */
    #[ManyToOne(entity: 'blog', api: ['admin-api' => true, 'store-api' => true])]
    public ?BlogEntity $blog = null;
}
