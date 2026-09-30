<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Customer\Extension;

use Shopwell\Core\Checkout\Customer\SalesChannel\CustomerRecoveryIsExpiredResponse;
use Shopwell\Core\Framework\Extensions\Extension;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

/**
 * Wraps `CustomerRecoveryIsExpiredRoute::load`. A listener on the `.pre` event
 * may resolve the expiry check itself (e.g. for an alternative account store),
 * assign a `CustomerRecoveryIsExpiredResponse` to `$result` and call
 * `stopPropagation()` to short-circuit the core flow.
 *
 * @extends Extension<CustomerRecoveryIsExpiredResponse>
 */
#[Package('checkout')]
final class CustomerRecoveryIsExpiredRouteExtension extends Extension
{
    public const NAME = 'customer-recovery-is-expired-route.load';

    /**
     * @internal shopwell owns the __constructor, but the properties are public API
     */
    public function __construct(
        /**
         * @public
         *
         * @description The submitted data (contains the recovery hash)
         */
        public readonly RequestDataBag $data,
        /**
         * @public
         *
         * @description The current sales-channel context
         */
        public readonly SalesChannelContext $context,
    ) {
    }
}
