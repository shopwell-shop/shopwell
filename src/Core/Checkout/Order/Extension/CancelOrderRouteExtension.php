<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Order\Extension;

use Shopwell\Core\Checkout\Order\SalesChannel\CancelOrderRouteResponse;
use Shopwell\Core\Framework\Extensions\Extension;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<CancelOrderRouteResponse>
 */
#[Package('checkout')]
final class CancelOrderRouteExtension extends Extension
{
    public const NAME = 'cancel-order-route.cancel';

    /**
     * @internal Shopwell owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly Request $request,
        public readonly SalesChannelContext $context,
    ) {
    }
}
