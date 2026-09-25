<?php declare(strict_types=1);

namespace Shopwell\Core\System\SalesChannel\SalesChannel;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<SalesChannelContext>
 */
#[Package('framework')]
class ContextLoadRouteResponse extends StoreApiResponse
{
    public function getContext(): SalesChannelContext
    {
        return $this->object;
    }
}
