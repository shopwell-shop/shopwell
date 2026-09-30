<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Customer\Extension;

use Shopwell\Core\Checkout\Customer\SalesChannel\CustomerResponse;
use Shopwell\Core\Framework\Extensions\Extension;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopwell\Core\Framework\Validation\DataValidationDefinition;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

/**
 * Wraps `RegisterRoute::register`. A listener on the `.pre` event may inspect the
 * submitted data and abort the registration by throwing, or fully replace the
 * registration by assigning `$result` and calling `stopPropagation()`.
 *
 * @extends Extension<CustomerResponse>
 */
#[Package('checkout')]
final class RegisterRouteExtension extends Extension
{
    public const NAME = 'register-route.register';

    /**
     * @internal shopwell owns the __constructor, but the properties are public API
     */
    public function __construct(
        /**
         * @public
         *
         * @description The submitted registration data
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
        /**
         * @public
         *
         * @description Additional validation definitions to merge into the registration validation
         */
        public readonly ?DataValidationDefinition $additionalValidationDefinitions,
    ) {
    }
}
