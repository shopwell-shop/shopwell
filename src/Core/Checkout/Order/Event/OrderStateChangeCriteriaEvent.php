<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Order\Event;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Event\ShopwellEvent;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class OrderStateChangeCriteriaEvent extends Event implements ShopwellEvent
{
    public function __construct(
        private readonly string $orderId,
        private readonly Criteria $criteria,
        private readonly Context $context,
    ) {
    }

    public function getOrderId(): string
    {
        return $this->orderId;
    }

    public function getCriteria(): Criteria
    {
        return $this->criteria;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
