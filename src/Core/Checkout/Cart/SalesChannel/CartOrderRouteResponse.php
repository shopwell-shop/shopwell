<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Cart\SalesChannel;

use Shopwell\Core\Checkout\Order\OrderEntity;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<OrderEntity>
 */
#[Package('checkout')]
class CartOrderRouteResponse extends StoreApiResponse
{
    public function getOrder(): OrderEntity
    {
        return $this->object;
    }
}
