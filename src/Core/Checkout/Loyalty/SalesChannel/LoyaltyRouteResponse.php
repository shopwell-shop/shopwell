<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Loyalty\SalesChannel;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\ArrayStruct;
use Shopwell\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<ArrayStruct<array<string, mixed>>>
 */
#[Package('checkout')]
class LoyaltyRouteResponse extends StoreApiResponse
{
    public function getLoyalty(): ArrayStruct
    {
        return $this->object;
    }
}
