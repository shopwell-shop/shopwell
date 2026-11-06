<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Product;

use Shopwell\Core\Framework\Log\Package;

/**
 * An extensible client environment, independent of the payment method and provider product.
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final readonly class PaymentScene
{
    public function __construct(
        public string $name,
        public string $device,
        public ?string $environment = null,
    ) {
    }
}
