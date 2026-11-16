<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Flow\Dispatching\Aware;

use Shopwell\Core\Framework\Event\IsFlowEventAware;
use Shopwell\Core\Framework\Log\Package;

#[Package('after-sales')]
#[IsFlowEventAware]
interface OrderDeliveryAware
{
    public const ORDER_DELIVERY_ID = 'orderDeliveryId';

    public const ORDER_DELIVERY = 'orderDelivery';

    public function getOrderDeliveryId(): string;
}
