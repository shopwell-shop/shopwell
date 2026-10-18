<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Wallet\Extension;

use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Wallet\SalesChannel\WalletTopUpRouteResponse;
use Shopwell\Core\Framework\Extensions\Extension;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

/**
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<WalletTopUpRouteResponse>
 */
#[Package('checkout')]
final class WalletTopUpStatusRouteExtension extends Extension
{
    public const NAME = 'wallet-top-up-status-route.load';

    /**
     * @internal Shopwell owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly string $topUpId,
        public readonly SalesChannelContext $context,
        public readonly CustomerEntity $customer,
    ) {
    }
}
