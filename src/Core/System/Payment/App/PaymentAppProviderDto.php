<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\App;

use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * One payment interface an app is authorized for, as sent by the administration.
 *
 * `credentials` are the plain values the app typed; they are sealed by the
 * {@see PaymentAppProvisioner} and never leave the server again. They are only stored for an app that
 * collects on its own account - `sourceAppId` is what decides that: it names the app whose account is
 * charged instead, the tenant of the platform in particular, and `null` means "an account of my own".
 *
 * @internal
 */
#[Package('framework')]
readonly class PaymentAppProviderDto
{
    /**
     * @param array<string, string> $credentials
     * @param array<string, string> $settings
     */
    public function __construct(
        #[Assert\NotBlank]
        public ?string $providerTechnicalName = null,
        public bool $enabled = false,
        public ?string $sourceAppId = null,
        public array $credentials = [],
        public array $settings = [],
    ) {
    }
}
