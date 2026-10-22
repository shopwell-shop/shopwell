<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Blog\Aggregate\BlogVideo;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<BlogVideoEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class BlogVideoCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'blog_video_collection';
    }

    protected function getExpectedClass(): string
    {
        return BlogVideoEntity::class;
    }
}
