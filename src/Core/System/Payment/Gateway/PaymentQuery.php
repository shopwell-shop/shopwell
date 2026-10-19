<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Gateway;

use Payum\Core\Request\Generic;
use Shopwell\Core\Framework\Log\Package;

/**
 * Asks the payment interface what happened to a payment, the counterpart of the notification: the
 * channel pushes a {@see \Payum\Core\Request\Notify}, the platform pulls a query - which is what the
 * reconciliation does for the payments whose notification never arrived.
 *
 * @internal
 */
#[Package('framework')]
final class PaymentQuery extends Generic
{
}
