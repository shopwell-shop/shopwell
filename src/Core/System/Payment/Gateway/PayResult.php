<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Gateway;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Api\Struct\PayParams;

/**
 * What a channel answered for a capture.
 *
 * @internal
 */
#[Package('framework')]
final readonly class PayResult
{
    public function __construct(
        public PayParams $params,
        public ?string $outPaymentNo = null,
    ) {
    }
}
