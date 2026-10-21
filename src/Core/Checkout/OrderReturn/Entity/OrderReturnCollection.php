<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\OrderReturn\Entity;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<OrderReturnEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
class OrderReturnCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'order_return_collection';
    }

    protected function getExpectedClass(): string
    {
        return OrderReturnEntity::class;
    }
}
