<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefund;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<OrderTransactionCaptureRefundEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class OrderTransactionCaptureRefundCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'order_transaction_capture_refund_collection';
    }

    protected function getExpectedClass(): string
    {
        return OrderTransactionCaptureRefundEntity::class;
    }
}
