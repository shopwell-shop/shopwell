<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Blog;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<BlogEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class BlogCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'blog_collection';
    }

    protected function getExpectedClass(): string
    {
        return BlogEntity::class;
    }
}
