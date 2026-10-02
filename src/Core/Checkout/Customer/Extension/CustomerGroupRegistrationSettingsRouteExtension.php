<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Customer\Extension;

use Shopwell\Core\Checkout\Customer\SalesChannel\CustomerGroupRegistrationSettingsRouteResponse;
use Shopwell\Core\Framework\Extensions\Extension;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

/**
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<CustomerGroupRegistrationSettingsRouteResponse>
 */
#[Package('checkout')]
final class CustomerGroupRegistrationSettingsRouteExtension extends Extension
{
    public const NAME = 'customer-group-registration-settings-route.load';

    /**
     * @internal Shopwell owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly string $customerGroupId,
        public readonly SalesChannelContext $context,
    ) {
    }
}
