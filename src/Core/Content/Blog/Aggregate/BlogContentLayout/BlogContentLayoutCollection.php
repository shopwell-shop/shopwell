<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Blog\Aggregate\BlogContentLayout;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @extends EntityCollection<BlogContentLayoutEntity>
 */
#[Package('discovery')]
class BlogContentLayoutCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'blog_content_layout_collection';
    }

    protected function getExpectedClass(): string
    {
        return BlogContentLayoutEntity::class;
    }
}
