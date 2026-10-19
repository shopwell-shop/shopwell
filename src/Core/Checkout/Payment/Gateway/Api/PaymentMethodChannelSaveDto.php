<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Payment\Gateway\Api;

use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * The body of the administration call that binds a payment method to a gateway channel.
 *
 * Only the channel is required. The app may be omitted, because the account the shop's own checkout
 * collects on is the default: an installation that never onboarded an app has exactly one tenant, and
 * asking for it would be asking the operator to repeat a fact the shop already knows.
 *
 * Both fields have a default, so a body that omits one reaches the constraints with its default
 * instead of failing to deserialize before the constraints are looked at.
 *
 * @internal
 */
#[Package('checkout')]
readonly class PaymentMethodChannelSaveDto
{
    public function __construct(
        #[Assert\NotBlank]
        public ?string $channelTechnicalName = null,
        public ?string $paymentAppId = null,
    ) {
    }
}
