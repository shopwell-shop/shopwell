<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Store\Event;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Event\ShopwellEvent;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('checkout')]
final class ShopwellAccountLogoutEvent implements ShopwellEvent
{
    public function __construct(
        private readonly Context $context,
    ) {
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
