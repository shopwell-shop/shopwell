<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\OrderReturn\SalesChannel;

use Shopwell\Core\Framework\Extensions\Extension;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * The seam a plugin listens on when it wants to change what a guest may return.
 *
 * A guest route has no customer, so only the request and the context are handed out: a plugin that
 * wants to narrow the reasons of a channel, or refuse a request for a category the shop excludes,
 * gets everything it needs from the request without the core knowing the rule.
 *
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<OrderReturnRouteResponse>
 */
#[Package('after-sales')]
final class OrderReturnGuestRouteExtension extends Extension
{
    public const NAME = 'order-return-guest-route';

    /**
     * @internal Shopwell owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly Request $request,
        public readonly SalesChannelContext $context,
    ) {
    }
}
