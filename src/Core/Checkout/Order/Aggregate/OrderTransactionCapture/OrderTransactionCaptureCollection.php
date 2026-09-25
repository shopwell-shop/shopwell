<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Order\Aggregate\OrderTransactionCapture;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<OrderTransactionCaptureEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class OrderTransactionCaptureCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'order_transaction_capture_collection';
    }

    protected function getExpectedClass(): string
    {
        return OrderTransactionCaptureEntity::class;
    }
}
