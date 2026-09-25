<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\Api\DTO;

use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class VerifyShop
{
    public function __construct(
        #[Assert\NotBlank]
        public string $runId,
        #[Assert\NotBlank]
        public string $token,
    ) {
    }
}
