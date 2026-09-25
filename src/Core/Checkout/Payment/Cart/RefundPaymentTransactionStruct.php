<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Payment\Cart;

use Shopwell\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class RefundPaymentTransactionStruct extends PaymentTransactionStruct
{
    public function __construct(
        protected string $refundId,
        string $orderTransactionId,
    ) {
        parent::__construct($orderTransactionId);
    }

    public function getRefundId(): string
    {
        return $this->refundId;
    }
}
