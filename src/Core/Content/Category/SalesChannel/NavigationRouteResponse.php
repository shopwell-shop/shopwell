<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Category\SalesChannel;

use Shopwell\Core\Content\Category\CategoryCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<CategoryCollection>
 */
#[Package('discovery')]
class NavigationRouteResponse extends StoreApiResponse
{
    public function getCategories(): CategoryCollection
    {
        return $this->object;
    }
}
