<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Media\SalesChannel;

use Shopwell\Core\Content\Media\MediaCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<MediaCollection>
 */
#[Package('discovery')]
class MediaRouteResponse extends StoreApiResponse
{
    public function getMediaCollection(): MediaCollection
    {
        return $this->object;
    }
}
