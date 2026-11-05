<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\App;

use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * An explicit grant; merchant account values are managed separately.
 *
 * @internal
 */
#[Package('framework')]
readonly class PaymentAppAccountDto
{
    public function __construct(
        #[Assert\NotBlank]
        public string $accountId = '',
        public bool $enabled = false,
        public int $position = 0,
    ) {
    }
}
