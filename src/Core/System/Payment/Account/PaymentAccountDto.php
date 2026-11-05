<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Account;

use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @internal
 */
#[Package('framework')]
readonly class PaymentAccountDto
{
    /**
     * @param list<string> $enabledProducts
     * @param array<string, mixed> $credentials
     * @param array<string, array<string, mixed>> $productParams
     * @param array<string, mixed> $settings
     */
    public function __construct(
        #[Assert\NotBlank]
        public string $providerTechnicalName = '',
        #[Assert\NotBlank]
        public string $name = '',
        public bool $active = false,
        public array $enabledProducts = [],
        public array $credentials = [],
        public array $productParams = [],
        public array $settings = [],
        public ?string $ownerAppId = null,
    ) {
    }
}
