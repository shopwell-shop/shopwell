<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Payment\Extension;

use Shopwell\Core\Checkout\Payment\SalesChannel\HandlePaymentMethodRouteResponse;
use Shopwell\Core\Framework\Extensions\Extension;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<HandlePaymentMethodRouteResponse>
 */
#[Package('checkout')]
final class HandlePaymentMethodRouteExtension extends Extension
{
    public const NAME = 'handle-payment-method-route.load';

    /**
     * @internal Shopwell owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly Request $request,
        public readonly SalesChannelContext $context,
    ) {
    }
}
