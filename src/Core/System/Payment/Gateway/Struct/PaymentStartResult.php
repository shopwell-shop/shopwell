<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Gateway\Struct;

use Shopwell\Core\Framework\Log\Package;

/**
 * What a channel answered for a capture.
 *
 * @internal
 */
#[Package('framework')]
final readonly class PaymentStartResult
{
    public function __construct(
        public PaymentHandover $params,
        public ?string $outPaymentNo = null,
    ) {
    }
}
