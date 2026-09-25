<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Payment\Event;

use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class FinalizePaymentOrderTransactionCriteriaEvent extends Event
{
    public function __construct(
        private readonly string $orderTransactionId,
        private readonly Criteria $criteria,
        private readonly SalesChannelContext $context
    ) {
    }

    public function getOrderTransactionId(): string
    {
        return $this->orderTransactionId;
    }

    public function getCriteria(): Criteria
    {
        return $this->criteria;
    }

    public function getContext(): SalesChannelContext
    {
        return $this->context;
    }
}
