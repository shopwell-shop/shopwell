<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Payment\Gateway\Api;

use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * The logical payment method, app and routing policy selected for checkout.
 * An omitted app uses the platform app.
 *
 * @internal
 */
#[Package('checkout')]
readonly class PaymentMethodChannelSaveDto
{
    public function __construct(
        #[Assert\NotBlank]
        public ?string $methodTechnicalName = null,
        public ?string $paymentAppId = null,
        #[Assert\NotBlank]
        public string $routingPolicy = 'priority',
    ) {
    }
}
