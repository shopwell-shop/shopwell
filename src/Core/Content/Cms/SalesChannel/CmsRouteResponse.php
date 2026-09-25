<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Cms\SalesChannel;

use Shopwell\Core\Content\Cms\CmsPageEntity;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<CmsPageEntity>
 */
#[Package('discovery')]
class CmsRouteResponse extends StoreApiResponse
{
    public function getCmsPage(): CmsPageEntity
    {
        return $this->object;
    }
}
