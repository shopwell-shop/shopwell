<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Product\SalesChannel\PurchaseLimit;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\StoreApiResponse;

/**
 * @codeCoverageIgnore
 *
 * @extends StoreApiResponse<ProductPurchaseLimitCollection>
 */
#[Package('inventory')]
class ProductPurchaseLimitRouteResponse extends StoreApiResponse
{
    public function getResult(): ProductPurchaseLimitCollection
    {
        return $this->object;
    }
}
