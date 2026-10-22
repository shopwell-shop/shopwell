<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Blog\Aggregate\BlogAuthor;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<BlogAuthorEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class BlogAuthorCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'blog_author_collection';
    }

    protected function getExpectedClass(): string
    {
        return BlogAuthorEntity::class;
    }
}
