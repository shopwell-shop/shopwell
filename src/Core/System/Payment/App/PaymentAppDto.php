<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\App;

use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * The body of the administration call that creates or updates an app.
 *
 * Every field has a default value, so a body that omits one reaches the constraints with its
 * default instead of failing to deserialize before the constraints are looked at.
 *
 * @internal
 */
#[Package('framework')]
readonly class PaymentAppDto
{
    /**
     * @param list<string> $allowedIps
     * @param list<PaymentAppProviderDto>|null $providers null leaves the authorizations untouched,
     *                                                    an empty list revokes all of them
     * @param list<PaymentAppChannelDto>|null $channels same for the enabled channels
     */
    public function __construct(
        public ?string $id = null,
        #[Assert\NotBlank]
        public ?string $technicalName = null,
        #[Assert\NotBlank]
        public ?string $name = null,
        public bool $active = false,
        public ?string $notifyUrl = null,
        public ?string $returnUrl = null,
        public array $allowedIps = [],
        #[Assert\Positive]
        public ?int $rateLimit = null,
        public ?string $appSecret = null,
        public ?array $providers = null,
        public ?array $channels = null,
    ) {
    }
}
