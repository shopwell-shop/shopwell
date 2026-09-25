<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Payment\Cart\PaymentHandler;

use Shopwell\Core\Framework\Log\Package;

#[Package('checkout')]
enum PaymentHandlerType
{
    case RECURRING;
    case REFUND;
}
