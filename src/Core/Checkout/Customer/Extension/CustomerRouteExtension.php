<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Customer\Extension;

use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Customer\SalesChannel\CustomerResponse;
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
 * @extends Extension<CustomerResponse>
 */
#[Package('checkout')]
final class CustomerRouteExtension extends Extension
{
    public const NAME = 'customer-route.load';

    /**
     * @internal Shopwell owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly Request $request,
        public readonly SalesChannelContext $context,
        public readonly Criteria $criteria,
        public readonly CustomerEntity $customer,
    ) {
    }
}
