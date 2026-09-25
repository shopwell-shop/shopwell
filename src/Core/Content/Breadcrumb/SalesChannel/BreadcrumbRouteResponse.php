<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Breadcrumb\SalesChannel;

use Shopwell\Core\Content\Breadcrumb\Struct\BreadcrumbCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<BreadcrumbCollection>
 */
#[Package('inventory')]
class BreadcrumbRouteResponse extends StoreApiResponse
{
    public function getBreadcrumbCollection(): BreadcrumbCollection
    {
        return $this->object;
    }
}
