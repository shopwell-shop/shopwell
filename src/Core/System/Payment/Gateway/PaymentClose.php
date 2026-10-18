<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Gateway;

use Payum\Core\Request\Generic;
use Shopwell\Core\Framework\Log\Package;

/**
 * Closes a payment at the interface: the counterpart of the capture, and the only way the platform can
 * make sure a payment it cancelled cannot be paid anymore.
 *
 * Payum ships no request of its own for it - its `Cancel` is the counterpart of an authorization, not
 * of a payment - so the platform names it here, the same way it names the {@see PaymentQuery}.
 *
 * The service that drives the gateway is {@see PaymentCloser}; this request only carries the model.
 *
 * @internal
 */
#[Package('framework')]
final class PaymentClose extends Generic
{
}
