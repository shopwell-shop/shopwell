<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\App;

use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * One payment interface an app is authorized for, as sent by the administration.
 *
 * `credentials` are the plain values the app typed; they are sealed by the
 * {@see PaymentAppProvisioner} and never leave the server again.
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
        public ?string $mode = null,
        public array $credentials = [],
        public array $settings = [],
    ) {
    }
}
