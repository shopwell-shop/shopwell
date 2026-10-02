<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Customer\Extension;

use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Framework\Extensions\Extension;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\NoContentResponse;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

/**
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<NoContentResponse>
 */
#[Package('checkout')]
final class SwitchDefaultAddressRouteExtension extends Extension
{
    public const NAME = 'switch-default-address-route.swap';

    /**
     * @internal Shopwell owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly string $addressId,
        public readonly string $type,
        public readonly SalesChannelContext $context,
        public readonly CustomerEntity $customer,
    ) {
    }
}
