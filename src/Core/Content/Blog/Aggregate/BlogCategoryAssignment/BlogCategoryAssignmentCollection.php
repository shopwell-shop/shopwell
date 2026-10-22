<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Blog\Aggregate\BlogCategoryAssignment;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<BlogCategoryAssignmentEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class BlogCategoryAssignmentCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'blog_category_assignment_collection';
    }

    protected function getExpectedClass(): string
    {
        return BlogCategoryAssignmentEntity::class;
    }
}
