<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Blog\Aggregate\BlogCategory;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<BlogCategoryEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class BlogCategoryCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'blog_category_collection';
    }

    protected function getExpectedClass(): string
    {
        return BlogCategoryEntity::class;
    }
}
