<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\App;

use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * One channel an app offers to its users, as sent by the administration.
 *
 * `params` are the plain values the app typed; they are sealed by the
 * {@see PaymentAppProvisioner} and never leave the server again.
 *
 * @internal
 */
#[Package('framework')]
readonly class PaymentAppChannelDto
{
    /**
     * @param array<string, string> $params
     * @param array<string, string> $settings
     */
    public function __construct(
        #[Assert\NotBlank]
        public string $channelTechnicalName = '',
        public bool $enabled = false,
        public array $params = [],
        public array $settings = [],
    ) {
    }
}
