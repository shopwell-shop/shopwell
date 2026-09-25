<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefundPosition;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<OrderTransactionCaptureRefundPositionEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class OrderTransactionCaptureRefundPositionCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'order_transaction_capture_refund_position_collection';
    }

    protected function getExpectedClass(): string
    {
        return OrderTransactionCaptureRefundPositionEntity::class;
    }
}
