<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\OrderReturn\Entity\Aggregate\OrderReturnReason;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<OrderReturnReasonEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
class OrderReturnReasonCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'order_return_reason_collection';
    }

    protected function getExpectedClass(): string
    {
        return OrderReturnReasonEntity::class;
    }
}
