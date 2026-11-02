<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Membership\SalesChannel;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\ArrayStruct;
use Shopwell\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<ArrayStruct<array<string, mixed>>>
 */
#[Package('checkout')]
class MembershipRouteResponse extends StoreApiResponse
{
    public function getMembership(): ArrayStruct
    {
        return $this->object;
    }
}
