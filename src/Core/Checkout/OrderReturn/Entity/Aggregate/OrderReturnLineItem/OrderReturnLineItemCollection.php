<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\OrderReturn\Entity\Aggregate\OrderReturnLineItem;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<OrderReturnLineItemEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
class OrderReturnLineItemCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'order_return_line_item_collection';
    }

    protected function getExpectedClass(): string
    {
        return OrderReturnLineItemEntity::class;
    }
}
