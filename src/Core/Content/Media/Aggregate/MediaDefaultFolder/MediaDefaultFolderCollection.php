<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Media\Aggregate\MediaDefaultFolder;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<MediaDefaultFolderEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class MediaDefaultFolderCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'media_default_folder_collection';
    }

    protected function getExpectedClass(): string
    {
        return MediaDefaultFolderEntity::class;
    }
}
