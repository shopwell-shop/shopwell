<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Media\Core\Strategy;

use Shopwell\Core\Content\Media\Core\Application\AbstractMediaPathStrategy;
use Shopwell\Core\Content\Media\Core\Params\MediaLocationStruct;
use Shopwell\Core\Content\Media\Core\Params\ThumbnailLocationStruct;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal Concrete implementation is not allowed to be decorated or extended. The implementation details can change
 */
#[Package('discovery')]
class PhysicalFilenamePathStrategy extends AbstractMediaPathStrategy
{
    public function name(): string
    {
        return 'physical_filename';
    }

    protected function value(MediaLocationStruct|ThumbnailLocationStruct $location): ?string
    {
        $media = $location instanceof ThumbnailLocationStruct ? $location->media : $location;

        $timestamp = $this->cacheBuster($location);

        return ($timestamp !== null ? $timestamp . '/' : '') . $media->fileName;
    }
}
