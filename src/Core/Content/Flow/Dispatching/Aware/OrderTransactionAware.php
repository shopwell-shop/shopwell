<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Flow\Dispatching\Aware;

use Shopwell\Core\Framework\Event\IsFlowEventAware;
use Shopwell\Core\Framework\Log\Package;

#[Package('after-sales')]
#[IsFlowEventAware]
interface OrderTransactionAware
{
    public const ORDER_TRANSACTION_ID = 'orderTransactionId';

    public const ORDER_TRANSACTION = 'orderTransaction';

    public function getOrderTransactionId(): string;
}
