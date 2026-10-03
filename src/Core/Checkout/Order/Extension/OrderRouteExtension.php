<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Order\Extension;

use Shopwell\Core\Checkout\Order\SalesChannel\OrderRouteResponse;
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
 * @extends Extension<OrderRouteResponse>
 */
#[Package('checkout')]
final class OrderRouteExtension extends Extension
{
    public const NAME = 'order-route.load';

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
