<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Shared\MailFlow\DataProvider;

use Shopwell\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopwell\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionDefinition;
use Shopwell\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @extends AbstractProvider<OrderTransactionEntity, OrderTransactionCollection>
 */
#[Package('after-sales')]
class OrderTransactionProvider extends AbstractProvider
{
    public function getEntityName(): string
    {
        return OrderTransactionDefinition::ENTITY_NAME;
    }

    protected function constructCriteria(string $entityId): Criteria
    {
        return new Criteria([$entityId]);
    }
}
