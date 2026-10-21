<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\OrderReturn\SalesChannel;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\ArrayStruct;
use Shopwell\Core\System\SalesChannel\StoreApiResponse;

/**
 * The one shape every return endpoint answers with.
 *
 * A return is read as a whole - the request, its positions, its conversation and what may still be
 * returned - so the endpoints hand out an assembled structure instead of the raw entity. That keeps
 * the storefront free of association names and lets a plugin add a field without changing a type.
 *
 * @extends StoreApiResponse<ArrayStruct<array<string, mixed>>>
 */
#[Package('after-sales')]
class OrderReturnRouteResponse extends StoreApiResponse
{
    public function getResult(): ArrayStruct
    {
        return $this->object;
    }
}
