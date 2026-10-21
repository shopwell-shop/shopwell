<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\OrderReturn\Entity\Aggregate\OrderReturnMessage;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<OrderReturnMessageEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
class OrderReturnMessageCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'order_return_message_collection';
    }

    protected function getExpectedClass(): string
    {
        return OrderReturnMessageEntity::class;
    }
}
