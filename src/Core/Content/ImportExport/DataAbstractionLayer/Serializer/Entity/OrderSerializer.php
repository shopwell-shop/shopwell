<?php declare(strict_types=1);

namespace Shopwell\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity;

use Shopwell\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopwell\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopwell\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopwell\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopwell\Core\Checkout\Order\OrderDefinition;
use Shopwell\Core\Content\ImportExport\Struct\Config;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\Struct;
use Shopwell\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;

#[Package('fundamentals@after-sales')]
class OrderSerializer extends EntitySerializer
{
    public function supports(string $entity): bool
    {
        return $entity === OrderDefinition::ENTITY_NAME;
    }

    public function serialize(Config $config, EntityDefinition $definition, $entity): iterable
    {
        if ($entity === null) {
            return;
        }

        if ($entity instanceof Struct) {
            $entity = $entity->jsonSerialize();
        }

        yield from parent::serialize($config, $definition, $entity);

        if (isset($entity['lineItems']) && $entity['lineItems'] instanceof OrderLineItemCollection) {
            $lineItems = $entity['lineItems']->getElements();
            $modifiedLineItems = [];

            foreach ($lineItems as $lineItem) {
                $lineItem = $lineItem->jsonSerialize();

                $modifiedLineItems[] = $lineItem['quantity'] . 'x ' . $lineItem['productId'];
            }

            $entity['lineItems'] = implode('|', $modifiedLineItems);
        }

        if (isset($entity['deliveries']) && $entity['deliveries'] instanceof OrderDeliveryCollection && $entity['deliveries']->count() > 0) {
            $entity['deliveries'] = $entity['deliveries']->first()?->jsonSerialize();

            if (!empty($entity['deliveries']['trackingCodes'])) {
                $entity['deliveries']['trackingCodes'] = implode('|', $entity['deliveries']['trackingCodes']);
            }

            if (isset($entity['deliveries']['shippingCosts']) && $entity['deliveries']['shippingCosts'] instanceof CalculatedPrice) {
                $entity['deliveries']['shippingCosts'] = $entity['deliveries']['shippingCosts']->getTotalPrice();
            }
        }

        if (isset($entity['transactions']) && $entity['transactions'] instanceof OrderTransactionCollection && $entity['transactions']->count() > 0) {
            $entity['transactions'] = $entity['transactions']->first()?->jsonSerialize();

            if (!empty($entity['transactions']['stateMachineState']) && $entity['transactions']['stateMachineState'] instanceof StateMachineStateEntity) {
                $entity['transactions']['stateMachineState'] = $entity['transactions']['stateMachineState']->jsonSerialize();
            }

            if (isset($entity['transactions']['amount']) && $entity['transactions']['amount'] instanceof CalculatedPrice) {
                $entity['transactions']['amount'] = $entity['transactions']['amount']->getTotalPrice();
            }
        }

        if (isset($entity['itemRounding']) && $entity['itemRounding'] instanceof CashRoundingConfig) {
            $entity['itemRounding'] = $entity['itemRounding']->jsonSerialize();
        }

        if (isset($entity['totalRounding']) && $entity['totalRounding'] instanceof CashRoundingConfig) {
            $entity['totalRounding'] = $entity['totalRounding']->jsonSerialize();
        }

        if (isset($entity['shippingCosts']) && $entity['shippingCosts'] instanceof CalculatedPrice) {
            $entity['shippingCosts'] = $entity['shippingCosts']->getTotalPrice();
        }

        yield from $entity;
    }
}
