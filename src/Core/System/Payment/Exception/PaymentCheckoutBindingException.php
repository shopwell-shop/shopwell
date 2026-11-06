<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Exception;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\PaymentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
class PaymentCheckoutBindingException extends PaymentException
{
    /**
     * A checkout payment method without a bound gateway channel cannot collect at all: the shopper
     * picked a method the administration never told how to reach an interface.
     */
    public static function paymentMethodChannelMissing(string $paymentMethodId): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::PAYMENT_METHOD_CHANNEL_MISSING,
            'The payment method "{{ paymentMethodId }}" has no gateway channel bound to it.',
            ['paymentMethodId' => $paymentMethodId]
        );
    }

    /**
     * The app/channel pair that is already bound to another payment method cannot be bound a second
     * time: the shopper's choice between the two methods would become meaningless and the money of one
     * of them would land in the account of the other. The database enforces the same rule over
     * (app, channel), this is the answer that names the method in the way of the one being configured.
     */
    public static function paymentMethodChannelTaken(string $paymentAppName, string $channelTechnicalName, string $paymentMethodId, string $paymentMethodName): self
    {
        return new self(
            Response::HTTP_CONFLICT,
            self::PAYMENT_METHOD_CHANNEL_TAKEN,
            'The payment app "{{ paymentAppName }}" already collects the channel "{{ channelTechnicalName }}" for the payment method "{{ paymentMethodName }}" ({{ paymentMethodId }}).',
            [
                'paymentAppName' => $paymentAppName,
                'channelTechnicalName' => $channelTechnicalName,
                'paymentMethodId' => $paymentMethodId,
                'paymentMethodName' => $paymentMethodName,
            ]
        );
    }

    /**
     * A channel that is opened by an sdk cannot be used by the storefront: it is pulled up by an app
     * that renders the payment itself, a browser has no sdk to hand the parameters to.
     */
    public static function channelNotAvailableInStorefront(string $technicalName): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::CHANNEL_NOT_AVAILABLE_IN_STOREFRONT,
            'The payment channel "{{ technicalName }}" cannot be used in the storefront checkout.',
            ['technicalName' => $technicalName]
        );
    }
}
