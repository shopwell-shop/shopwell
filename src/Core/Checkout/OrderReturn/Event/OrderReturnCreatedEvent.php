<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\OrderReturn\Event;

use Shopwell\Core\Checkout\OrderReturn\Entity\OrderReturnEntity;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Event\ShopwellEvent;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched after a return was opened and is waiting for the decision of the shop.
 *
 * A plugin that wants to notify somebody, open a ticket in another system or hand out points listens
 * here and does not have to touch the return itself.
 */
#[Package('after-sales')]
class OrderReturnCreatedEvent extends Event implements ShopwellEvent
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
