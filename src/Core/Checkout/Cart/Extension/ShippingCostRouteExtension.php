<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Cart\Extension;

use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Cart\SalesChannel\ShippingCostRouteResponse;
use Shopwell\Core\Framework\Extensions\Extension;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

/**
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<ShippingCostRouteResponse>
 */
#[Package('checkout')]
final class ShippingCostRouteExtension extends Extension
{
    public const NAME = 'shipping-cost-route.shipping-costs-cart';

    /**
     * @internal Shopwell owns the constructor; the properties are public API.
     *
     * @param non-empty-list<string>|null $availableShippingMethodIds
     */
    public function __construct(
        public readonly Cart $cart,
        public readonly SalesChannelContext $salesChannelContext,
        public readonly ?array $availableShippingMethodIds,
    ) {
    }
}
