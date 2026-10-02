<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Customer\Extension;

use Shopwell\Core\Framework\Extensions\Extension;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopwell\Core\System\SalesChannel\ContextTokenResponse;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

/**
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<ContextTokenResponse>
 */
#[Package('checkout')]
final class ImitateCustomerRouteExtension extends Extension
{
    public const NAME = 'imitate-customer-route.imitate-customer-login';

    /**
     * @internal Shopwell owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly RequestDataBag $data,
        public readonly SalesChannelContext $context,
    ) {
    }
}
