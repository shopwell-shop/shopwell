<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Cart\Delivery;

use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Cart\CartValidatorInterface;
use Shopwell\Core\Checkout\Cart\Error\ErrorCollection;
use Shopwell\Core\Checkout\Shipping\Cart\Error\ShippingMethodBlockedError;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
class DeliveryValidator implements CartValidatorInterface
{
    public function validate(Cart $cart, ErrorCollection $errors, SalesChannelContext $context): void
    {
        foreach ($cart->getDeliveries() as $delivery) {
            $shippingMethod = $delivery->getShippingMethod();
            $ruleId = $shippingMethod->getAvailabilityRuleId();

            $matches = \in_array($ruleId, $context->getRuleIds(), true) || $ruleId === null;

            if ($matches && $shippingMethod->getActive()) {
                continue;
            }

            $errors->add(
                new ShippingMethodBlockedError(
                    id: $shippingMethod->getId(),
                    name: (string) $shippingMethod->getTranslation('name'),
                    reason: 'rule not matching or inactive',
                )
            );
        }
    }
}
