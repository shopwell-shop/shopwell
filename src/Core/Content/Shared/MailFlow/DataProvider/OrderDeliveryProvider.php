<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Shared\MailFlow\DataProvider;

use Shopwell\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopwell\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryDefinition;
use Shopwell\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @extends AbstractProvider<OrderDeliveryEntity, OrderDeliveryCollection>
 */
#[Package('after-sales')]
class OrderDeliveryProvider extends AbstractProvider
{
    public function getEntityName(): string
    {
        return OrderDeliveryDefinition::ENTITY_NAME;
    }

    protected function constructCriteria(string $entityId): Criteria
    {
        return (new Criteria([$entityId]))
            ->addAssociations([
                'positions.orderLineItem',
                'shippingMethod',
                'stateMachineState',
                'shippingOrderAddress.country',
                'shippingOrderAddress.countryState',
            ]);
    }
}
