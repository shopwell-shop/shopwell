<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Gateway\Context\Extension;

use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Framework\Extensions\Extension;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\ContextTokenResponse;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<ContextTokenResponse>
 */
#[Package('framework')]
final class ContextGatewayRouteExtension extends Extension
{
    public const NAME = 'context-gateway-route.load';

    /**
     * @internal Shopwell owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly Request $request,
        public readonly Cart $cart,
        public readonly SalesChannelContext $context,
    ) {
    }
}
