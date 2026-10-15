<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Loyalty\Extension;

use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Loyalty\SalesChannel\LoyaltyRedeemRouteResponse;
use Shopwell\Core\Framework\Extensions\Extension;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<LoyaltyRedeemRouteResponse>
 */
#[Package('checkout')]
final class LoyaltyRedeemRouteExtension extends Extension
{
    public const NAME = 'loyalty-redeem-route.redeem';

    /**
     * @internal Shopwell owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly Request $request,
        public readonly Cart $cart,
        public readonly SalesChannelContext $context,
        public readonly CustomerEntity $customer,
    ) {
    }
}
