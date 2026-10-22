<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Blog\Aggregate\BlogComment;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<BlogCommentEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class BlogCommentCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'blog_comment_collection';
    }

    protected function getExpectedClass(): string
    {
        return BlogCommentEntity::class;
    }
}
