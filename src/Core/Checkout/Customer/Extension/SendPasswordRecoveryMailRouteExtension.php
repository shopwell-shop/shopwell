<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Customer\Extension;

use Shopwell\Core\Framework\Extensions\Extension;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\System\SalesChannel\SuccessResponse;

/**
 * Wraps `SendPasswordRecoveryMailRoute::sendRecoveryMail`. A listener on the
 * `.pre` event may handle the recovery request itself (e.g. for an alternative
 * account store), assign a `SuccessResponse` to `$result` and call
 * `stopPropagation()` to short-circuit the core flow.
 *
 * @extends Extension<SuccessResponse>
 */
#[Package('checkout')]
final class SendPasswordRecoveryMailRouteExtension extends Extension
{
    public const NAME = 'send-password-recovery-mail-route.send-recovery-mail';

    /**
     * @internal shopwell owns the __constructor, but the properties are public API
     */
    public function __construct(
        /**
         * @public
         *
         * @description The submitted recovery data (contains the e-mail address)
         */
        public readonly RequestDataBag $data,
        /**
         * @public
         *
         * @description The current sales-channel context
         */
        public readonly SalesChannelContext $context,
        /**
         * @public
         *
         * @description Whether the submitted storefrontUrl is validated against the sales-channel domains
         */
        public readonly bool $validateStorefrontUrl,
    ) {
    }
}
