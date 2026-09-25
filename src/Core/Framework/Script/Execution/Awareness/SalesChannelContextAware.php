<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Script\Execution\Awareness;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

/**
 * Can be implemented by hooks to provide services with the sales channel context.
 * The services can inject the context beforehand and provide a narrow API to the developer.
 *
 * @internal
 */
#[Package('framework')]
interface SalesChannelContextAware
{
    public function getSalesChannelContext(): SalesChannelContext;
}
