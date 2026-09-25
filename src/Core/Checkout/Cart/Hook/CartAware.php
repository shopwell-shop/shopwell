<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Cart\Hook;

use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Script\Execution\Awareness\SalesChannelContextAware;

/**
 * @internal Not intended for use in plugins
 * Can be implemented by hooks to provide services with the sales channel context.
 * The services can inject the context beforehand and provide a narrow API to the developer.
 */
#[Package('checkout')]
interface CartAware extends SalesChannelContextAware
{
    public function getCart(): Cart;
}
