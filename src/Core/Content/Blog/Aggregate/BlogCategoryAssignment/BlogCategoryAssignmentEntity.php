<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Blog\Aggregate\BlogCategoryAssignment;

use Shopwell\Core\Content\Blog\Aggregate\BlogCategory\BlogCategoryEntity;
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
 * Filing an entry into a category - the row that makes the many to many relation between the two.
 *
 * An entry is in many categories and a category holds many entries, and the dal has no many to many
 * association between attribute based entities. The row is an entity of its own with an own id
 * instead of a plain mapping table: it can carry editorial data later (a manual position inside the
 * category, the moment the entry was filed) without a migration that rebuilds the primary key.
 *
 * The unique key over `blog_id` and `blog_category_id` is what keeps an entry from being filed into
 * the same category twice; the assignment is written by the administration and is idempotent.
 *
 * @codeCoverageIgnore
 */
#[Package('discovery')]
#[Entity('blog_category_assignment', since: '6.7.18.0', collectionClass: BlogCategoryAssignmentCollection::class)]
class BlogCategoryAssignmentEntity extends EntityStruct
{
    final public const ENTITY_NAME = 'blog_category_assignment';

    #[PrimaryKey]
    #[Field(type: FieldType::UUID, api: ['admin-api' => true, 'store-api' => true])]
    public string $id;

    #[Required]
    #[ForeignKey(entity: 'blog', api: ['admin-api' => true, 'store-api' => true])]
    public string $blogId;

    #[Required]
    #[ForeignKey(entity: 'blog_category', api: ['admin-api' => true, 'store-api' => true])]
    public string $blogCategoryId;

    /**
     * The assignment belongs to its entry, the cascade is declared on the reverse side in BlogEntity
     * - the dal forbids a delete flag on the many to one side.
     */
    #[ManyToOne(entity: 'blog', api: ['admin-api' => true, 'store-api' => true])]
    public ?BlogEntity $blog = null;

    #[ManyToOne(entity: 'blog_category', api: ['admin-api' => true, 'store-api' => true])]
    public ?BlogCategoryEntity $blogCategory = null;
}
