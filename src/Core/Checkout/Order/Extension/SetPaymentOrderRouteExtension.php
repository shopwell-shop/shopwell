<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Order\Extension;

use Shopwell\Core\Checkout\Order\SalesChannel\SetPaymentOrderRouteResponse;
use Shopwell\Core\Framework\Extensions\Extension;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<SetPaymentOrderRouteResponse>
 */
#[Package('checkout')]
final class SetPaymentOrderRouteExtension extends Extension
{
    public const NAME = 'set-payment-order-route.set-payment';

    /**
     * @internal Shopwell owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly Request $request,
        public readonly SalesChannelContext $context,
    ) {
    }
}
