<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Routing;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Exception\PaymentProviderException;
use Shopwell\Core\System\Payment\Product\PaymentInteraction;
use Shopwell\Core\System\Payment\Product\PaymentProduct;

/**
 * @internal
 */
#[Package('framework')]
class PaymentProductEligibility
{
    public function isEligible(PaymentProduct $product, PaymentContext $context): bool
    {
        try {
            $this->assertEligible($product, $context);

            return true;
        } catch (PaymentProviderException) {
            return false;
        }
    }

    public function assertEligible(PaymentProduct $product, PaymentContext $context): void
    {
        if (!$product->supportsCurrency($context->currency)) {
            throw PaymentProviderException::currencyNotSupported($product->technicalName, $context->currency);
        }
        if (!$product->supportsDevice($context->device)) {
            throw PaymentProviderException::deviceNotSupported($product->technicalName, $context->device);
        }
        if ($this->interactions($product, $context) === []) {
            throw PaymentProviderException::contextNotSupported($product->technicalName, $context->scene->name);
        }
        if ($product->scenes !== []) {
            $matches = false;
            foreach ($product->scenes as $scene) {
                if ($scene->name === $context->scene->name && $scene->device === $context->scene->device
                    && ($scene->environment === null || $scene->environment === $context->scene->environment)) {
                    $matches = true;
                    break;
                }
            }
            if (!$matches) {
                throw PaymentProviderException::contextNotSupported($product->technicalName, $context->scene->name);
            }
        }
        $required = $product->requiredRuntimeParams();
        if ($required !== []) {
            foreach ($context->accountRuntimeParams as $products) {
                $values = $products[$product->technicalName] ?? [];
                $complete = true;
                foreach ($required as $name) {
                    $value = $values[$name] ?? null;
                    if (!\is_scalar($value) || trim((string) $value) === '') {
                        $complete = false;
                        break;
                    }
                }
                if ($complete) {
                    return;
                }
            }

            throw PaymentProviderException::missingRuntimeParams($product->technicalName, $required);
        }
    }

    /**
     * @return list<string>
     */
    public function interactions(PaymentProduct $product, PaymentContext $context): array
    {
        return array_values(array_filter(
            $product->interactions,
            static fn (string $interaction): bool => \in_array($interaction, $context->supportedInteractions, true)
            && ($interaction !== PaymentInteraction::SDK || \in_array($product->technicalName, $context->supportedSdkProducts, true))
        ));
    }
}
