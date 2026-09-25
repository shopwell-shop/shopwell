<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Payment\Cart\PaymentHandler;

use Shopwell\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
class PrePayment extends DefaultPayment
{
    public function supports(PaymentHandlerType $type, string $paymentMethodId, Context $context): bool
    {
        return $type === PaymentHandlerType::RECURRING;
    }

    public function recurring(PaymentTransactionStruct $transaction, Context $context): void
    {
    }
}
