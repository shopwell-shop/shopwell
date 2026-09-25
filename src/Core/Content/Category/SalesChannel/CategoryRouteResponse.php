<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Category\SalesChannel;

use Shopwell\Core\Content\Category\CategoryEntity;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<CategoryEntity>
 */
#[Package('discovery')]
class CategoryRouteResponse extends StoreApiResponse
{
    public function getCategory(): CategoryEntity
    {
        return $this->object;
    }
}
