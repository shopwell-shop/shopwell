<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Blog\Aggregate\BlogComment;

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
 * A comment below an entry, written by a customer or by a guest.
 *
 * The shape follows the product review, because a moderator who learned one has learned both: a
 * status that only the administration flips, an optional customer, and free text plus the two guest
 * fields. What a review has and a comment does not is the star rating - a comment on an article
 * carries no points, so there is no `points` column and nothing to aggregate.
 *
 * `status` is `false` until somebody approves it, and it is the storefront that has to filter on it.
 * The column is a plain bool and not a state machine: a comment has two states, and a state machine
 * for two states is a table read on every listing.
 *
 * Replies are one level deep. `parentId` therefore points at a root comment, never at a reply - the
 * storefront groups by it in php and the write route rejects a parent that already has one.
 *
 * The references to `customer`, `sales_channel` and `language` are kept as plain foreign keys without
 * an association on purpose: the dal demands a reverse one-to-many for every many-to-one, and this
 * domain must not grow fields on definitions it does not own. The same reason applies to `parentId` -
 * a self referencing association is a known way to make the dal load forever.
 *
 * @codeCoverageIgnore
 */
#[Package('discovery')]
#[Entity('blog_comment', since: '6.7.18.0', collectionClass: BlogCommentCollection::class)]
class BlogCommentEntity extends EntityStruct
{
    final public const ENTITY_NAME = 'blog_comment';

    #[PrimaryKey]
    #[Field(type: FieldType::UUID, api: ['admin-api' => true, 'store-api' => true])]
    public string $id;

    #[Required]
    #[ForeignKey(entity: 'blog', api: ['admin-api' => true, 'store-api' => true])]
    public string $blogId;

    /**
     * The comment this one answers, null for a root comment. Only one level is supported.
     */
    #[ForeignKey(entity: 'blog_comment', api: ['admin-api' => true, 'store-api' => true])]
    public ?string $parentId = null;

    /**
     * The logged in customer, null for a guest comment. A comment keeps its author even after the
     * account is deleted, which is why the id is nullable instead of cascading.
     */
    #[ForeignKey(entity: 'customer', api: ['admin-api' => true, 'store-api' => true])]
    public ?string $customerId = null;

    /**
     * The channel the comment was written in. A comment belongs to the channel it was posted on, so a
     * shop that runs two very different storefronts does not mix them.
     */
    #[Required]
    #[ForeignKey(entity: 'sales_channel', api: ['admin-api' => true, 'store-api' => true])]
    public string $salesChannelId;

    /**
     * The language the comment was written in. The text is stored as typed and never translated - a
     * comment is what somebody said, not content the shop maintains.
     */
    #[Required]
    #[ForeignKey(entity: 'language', api: ['admin-api' => true, 'store-api' => true])]
    public string $languageId;

    /**
     * The display name of a guest. Null for a customer comment, where the name comes from the account.
     */
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => true], maxLength: 255)]
    public ?string $externalUser = null;

    /**
     * The address of a guest. Never rendered - it exists so a moderator can reach the author and so
     * the flood protection can count what one address posted.
     */
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => true], maxLength: 255)]
    public ?string $externalEmail = null;

    /**
     * An optional headline above the text. Unlike a review title it is not required: a comment is a
     * sentence in a thread, and forcing a headline on it produces "Re: Re: Re:".
     */
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => true], maxLength: 255)]
    public ?string $title = null;

    #[Required]
    #[Field(type: FieldType::TEXT, api: ['admin-api' => true, 'store-api' => true])]
    public string $content;

    /**
     * Whether a moderator approved the comment. False on creation, flipped in the administration, and
     * the storefront shows only the approved ones.
     */
    #[Field(type: FieldType::BOOL, api: ['admin-api' => true, 'store-api' => true])]
    public bool $status = false;

    /**
     * The entry the comment belongs to. The cascade is declared on the reverse side in BlogEntity -
     * the dal forbids a delete flag on a many-to-one association.
     */
    #[ManyToOne(entity: 'blog', api: ['admin-api' => true, 'store-api' => true])]
    public ?BlogEntity $blog = null;
}
