<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\OrderReturn\Event;

use Shopwell\Core\Checkout\OrderReturn\Entity\OrderReturnEntity;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Event\ShopwellEvent;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched when a return reached its end, i.e. the money went back and the goods are settled.
 */
#[Package('after-sales')]
class OrderReturnCompletedEvent extends Event implements ShopwellEvent
{
    public function __construct(
        private readonly OrderReturnEntity $orderReturn,
        private readonly Context $context,
    ) {
    }

    public function getOrderReturn(): OrderReturnEntity
    {
        return $this->orderReturn;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
