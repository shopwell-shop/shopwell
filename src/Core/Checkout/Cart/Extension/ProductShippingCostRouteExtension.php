<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Cart\Extension;

use Shopwell\Core\Checkout\Cart\SalesChannel\ShippingCostRouteResponse;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
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
final class ProductShippingCostRouteExtension extends Extension
{
    public const NAME = 'product-shipping-cost-route.shipping-costs-by-product';

    /**
     * @internal Shopwell owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly string $productId,
        public readonly Criteria $criteria,
        public readonly SalesChannelContext $salesChannelContext,
    ) {
    }
}
