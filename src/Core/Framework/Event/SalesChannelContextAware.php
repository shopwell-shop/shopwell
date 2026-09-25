<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Event;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

/**
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
#[IsFlowEventAware]
interface SalesChannelContextAware extends SalesChannelAware
{
    public const SALES_CHANNEL_CONTEXT = 'salesChannelContext';

    public const SALES_CHANNEL_DOMAIN_ID = 'salesChannelDomainId';

    public const SALES_CHANNEL_CUSTOMER_ID = 'salesChannelCustomerId';

    public function getSalesChannelContext(): SalesChannelContext;
}
