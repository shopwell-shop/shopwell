<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Media\Thumbnail;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\Collection;

/**
 * @extends Collection<ExternalThumbnailData>
 *
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class ExternalThumbnailCollection extends Collection
{
    protected function getExpectedClass(): string
    {
        return ExternalThumbnailData::class;
    }
}
