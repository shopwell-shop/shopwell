<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Payment\Cart;

use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Cart\CartValidatorInterface;
use Shopwell\Core\Checkout\Cart\Error\ErrorCollection;
use Shopwell\Core\Checkout\Payment\Cart\Error\PaymentMethodBlockedError;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
class PaymentMethodValidator implements CartValidatorInterface
{
    public function validate(Cart $cart, ErrorCollection $errors, SalesChannelContext $context): void
    {
        $paymentMethod = $context->getPaymentMethod();
        if (!$paymentMethod->getActive()) {
            $errors->add(
                new PaymentMethodBlockedError(
                    id: $paymentMethod->getId(),
                    name: (string) $paymentMethod->getTranslation('name'),
                    reason: 'inactive',
                )
            );
        }

        $ruleId = $paymentMethod->getAvailabilityRuleId();

        if ($ruleId && !\in_array($ruleId, $context->getRuleIds(), true)) {
            $errors->add(
                new PaymentMethodBlockedError(
                    id: $paymentMethod->getId(),
                    name: (string) $paymentMethod->getTranslation('name'),
                    reason: 'rule not matching',
                )
            );
        }

        if (!\in_array($paymentMethod->getId(), $context->getSalesChannel()->getPaymentMethodIds() ?? [], true)) {
            $errors->add(
                new PaymentMethodBlockedError(
                    id: $paymentMethod->getId(),
                    name: (string) $paymentMethod->getTranslation('name'),
                    reason: 'not allowed',
                )
            );
        }
    }
}
