<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\OrderReturn\SalesChannel;

use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Framework\Extensions\Extension;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * The seam a plugin listens on when it wants to change what a customer may return.
 *
 * It is published by the list and by the create endpoint and carries the request, so a plugin can
 * narrow the criteria of the list (a category the shop excludes, a customer group that may not
 * return) or reject a request it does not allow - without the core knowing that such a rule exists.
 *
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<OrderReturnRouteResponse>
 */
#[Package('after-sales')]
final class OrderReturnRouteExtension extends Extension
{
    public const NAME = 'order-return-route';

    /**
     * @internal Shopwell owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly Request $request,
        public readonly SalesChannelContext $context,
        public readonly CustomerEntity $customer,
    ) {
    }
}
