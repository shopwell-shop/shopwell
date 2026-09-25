<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Media\Aggregate\MediaThumbnail;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<MediaThumbnailEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class MediaThumbnailCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'media_thumbnail_collection';
    }

    protected function getExpectedClass(): string
    {
        return MediaThumbnailEntity::class;
    }
}
