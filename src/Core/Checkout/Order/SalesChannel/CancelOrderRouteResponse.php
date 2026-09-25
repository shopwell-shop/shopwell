<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Order\SalesChannel;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\StoreApiResponse;
use Shopwell\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;

/**
 * @extends StoreApiResponse<StateMachineStateEntity>
 */
#[Package('checkout')]
class CancelOrderRouteResponse extends StoreApiResponse
{
    public function getState(): StateMachineStateEntity
    {
        return $this->object;
    }
}
