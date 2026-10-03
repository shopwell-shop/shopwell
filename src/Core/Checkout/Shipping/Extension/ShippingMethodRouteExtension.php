<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Shipping\Extension;

use Shopwell\Core\Checkout\Shipping\SalesChannel\ShippingMethodRouteResponse;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Extensions\Extension;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<ShippingMethodRouteResponse>
 */
#[Package('checkout')]
final class ShippingMethodRouteExtension extends Extension
{
    public const NAME = 'shipping-method-route.load';

    /**
     * @internal Shopwell owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly Request $request,
        public readonly SalesChannelContext $context,
        public readonly Criteria $criteria,
    ) {
    }
}
