<?php declare(strict_types=1);

namespace Shopwell\Core\System\SalesChannel\Event;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Event\NestedEvent;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
class SalesChannelContextRestoredEvent extends NestedEvent
{
    public function __construct(
        private readonly SalesChannelContext $restoredContext,
        private readonly SalesChannelContext $currentContext
    ) {
    }

    public function getRestoredSalesChannelContext(): SalesChannelContext
    {
        return $this->restoredContext;
    }

    public function getContext(): Context
    {
        return $this->restoredContext->getContext();
    }

    public function getCurrentSalesChannelContext(): SalesChannelContext
    {
        return $this->currentContext;
    }
}
