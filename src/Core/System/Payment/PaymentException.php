<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment;

use Shopwell\Core\Framework\HttpException;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Exception\PaymentAppException;
use Shopwell\Core\System\Payment\Exception\PaymentCheckoutBindingException;
use Shopwell\Core\System\Payment\Exception\PaymentGatewayException;
use Shopwell\Core\System\Payment\Exception\PaymentNotificationException;
use Shopwell\Core\System\Payment\Exception\PaymentOrderException;
use Shopwell\Core\System\Payment\Exception\PaymentProviderException;
use Shopwell\Core\System\Payment\Exception\PaymentSubscriptionException;
use Shopwell\Core\System\Payment\Exception\PaymentTransferException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class PaymentException extends HttpException
{
    public const UNKNOWN_PROVIDER = 'PAYMENT__UNKNOWN_PROVIDER';
    public const UNKNOWN_CHANNEL = 'PAYMENT__UNKNOWN_CHANNEL';
    public const DUPLICATE_CHANNEL = 'PAYMENT__DUPLICATE_CHANNEL';
    public const DUPLICATE_PROVIDER = 'PAYMENT__DUPLICATE_PROVIDER';
    public const AMBIGUOUS_PROVIDER_PRIORITY = 'PAYMENT__AMBIGUOUS_PROVIDER_PRIORITY';
    public const INVALID_PROVIDER_NAME = 'PAYMENT__INVALID_PROVIDER_NAME';
    public const INVALID_PROVIDER_DECLARATION = 'PAYMENT__INVALID_PROVIDER_DECLARATION';
    public const STATE_MACHINE_INVALID = 'PAYMENT__STATE_MACHINE_INVALID';

    public const PROVIDER_NOT_ENABLED = 'PAYMENT__PROVIDER_NOT_ENABLED';
    public const PROVIDER_NOT_CONFIGURED = 'PAYMENT__PROVIDER_NOT_CONFIGURED';
    public const PROVIDER_INHERITANCE_CYCLE = 'PAYMENT__PROVIDER_INHERITANCE_CYCLE';
    public const PROVIDER_SOURCE_APP_MISSING = 'PAYMENT__PROVIDER_SOURCE_APP_MISSING';
    public const CHANNEL_NOT_ENABLED = 'PAYMENT__CHANNEL_NOT_ENABLED';
    public const CHANNEL_NOT_CONFIGURED = 'PAYMENT__CHANNEL_NOT_CONFIGURED';
    public const CHANNEL_DISABLED = 'PAYMENT__CHANNEL_DISABLED';
    public const CHANNEL_NOT_SUPPORTED = 'PAYMENT__CHANNEL_NOT_SUPPORTED';
    public const CURRENCY_NOT_SUPPORTED = 'PAYMENT__CURRENCY_NOT_SUPPORTED';
    public const DEVICE_NOT_SUPPORTED = 'PAYMENT__DEVICE_NOT_SUPPORTED';
    public const MISSING_RUNTIME_PARAMS = 'PAYMENT__MISSING_RUNTIME_PARAMS';

    public const MISSING_AUTH_PARAMETERS = 'PAYMENT__MISSING_AUTH_PARAMETERS';
    public const INVALID_SIGNATURE = 'PAYMENT__INVALID_SIGNATURE';
    public const EXPIRED_TIMESTAMP = 'PAYMENT__EXPIRED_TIMESTAMP';
    public const FOREIGN_APP = 'PAYMENT__FOREIGN_APP';
    public const APP_NOT_FOUND = 'PAYMENT__APP_NOT_FOUND';
    public const APP_DISABLED = 'PAYMENT__APP_DISABLED';
    public const PLATFORM_TENANT_MISSING = 'PAYMENT__PLATFORM_TENANT_MISSING';

    public const MISSING_PARAMETER = 'PAYMENT__MISSING_PARAMETER';

    public const ORDER_NOT_FOUND = 'PAYMENT__ORDER_NOT_FOUND';
    public const ORDER_CONFLICT = 'PAYMENT__ORDER_CONFLICT';
    public const ORDER_NOT_PAYABLE = 'PAYMENT__ORDER_NOT_PAYABLE';
    public const ORDER_ALREADY_CLOSED = 'PAYMENT__ORDER_ALREADY_CLOSED';
    public const ORDER_ALREADY_PAID = 'PAYMENT__ORDER_ALREADY_PAID';
    public const ORDER_NOT_EXPIRABLE = 'PAYMENT__ORDER_NOT_EXPIRABLE';
    public const INVALID_AMOUNT = 'PAYMENT__INVALID_AMOUNT';
    public const AMOUNT_MISMATCH = 'PAYMENT__AMOUNT_MISMATCH';
    public const CURRENCY_MISMATCH = 'PAYMENT__CURRENCY_MISMATCH';

    public const REFUND_NOT_ALLOWED = 'PAYMENT__REFUND_NOT_ALLOWED';
    public const REFUND_AMOUNT_EXCEEDS_ORDER = 'PAYMENT__REFUND_AMOUNT_EXCEEDS_ORDER';
    public const REFUND_NOT_FOUND = 'PAYMENT__REFUND_NOT_FOUND';

    public const NOTIFICATION_INVALID = 'PAYMENT__NOTIFICATION_INVALID';
    public const NOTIFICATION_ORDER_UNKNOWN = 'PAYMENT__NOTIFICATION_ORDER_UNKNOWN';
    public const NOTIFICATION_NOT_FOUND = 'PAYMENT__NOTIFICATION_NOT_FOUND';
    public const WEBHOOK_DELIVERY_NOT_FOUND = 'PAYMENT__WEBHOOK_DELIVERY_NOT_FOUND';

    public const GATEWAY_ERROR = 'PAYMENT__GATEWAY_ERROR';
    public const MISSING_CREDENTIALS = 'PAYMENT__MISSING_CREDENTIALS';
    public const GATEWAY_RESULT_MISSING = 'PAYMENT__GATEWAY_RESULT_MISSING';

    public const UNSUPPORTED_API = 'PAYMENT__UNSUPPORTED_API';
    public const ACTION_WITHOUT_API = 'PAYMENT__ACTION_WITHOUT_API';
    public const PROVIDER_LACKS_CAPABILITY = 'PAYMENT__PROVIDER_LACKS_CAPABILITY';

    public const PAYMENT_METHOD_CHANNEL_MISSING = 'PAYMENT__PAYMENT_METHOD_CHANNEL_MISSING';
    public const PAYMENT_METHOD_CHANNEL_TAKEN = 'PAYMENT__PAYMENT_METHOD_CHANNEL_TAKEN';
    public const CHANNEL_NOT_AVAILABLE_IN_STOREFRONT = 'PAYMENT__CHANNEL_NOT_AVAILABLE_IN_STOREFRONT';
    public const PLATFORM_TENANT_READ_ONLY = 'PAYMENT__PLATFORM_TENANT_READ_ONLY';

    public const CYCLE_NOT_EXPRESSIBLE = 'PAYMENT__CYCLE_NOT_EXPRESSIBLE';
    public const INVALID_PERIOD_UNIT = 'PAYMENT__INVALID_PERIOD_UNIT';
    public const INVALID_PERIOD_COUNT = 'PAYMENT__INVALID_PERIOD_COUNT';
    public const CYCLE_NOT_FOUND = 'PAYMENT__CYCLE_NOT_FOUND';
    public const CYCLE_CONFLICT = 'PAYMENT__CYCLE_CONFLICT';
    public const CYCLE_NOT_REARMABLE = 'PAYMENT__CYCLE_NOT_REARMABLE';
    public const SUBSCRIPTION_NOT_FOUND = 'PAYMENT__SUBSCRIPTION_NOT_FOUND';

    public const TRANSFER_NOT_FOUND = 'PAYMENT__TRANSFER_NOT_FOUND';
    public const TRANSFER_CONFLICT = 'PAYMENT__TRANSFER_CONFLICT';
    public const TRANSFER_RECIPIENT_NOT_FOUND = 'PAYMENT__TRANSFER_RECIPIENT_NOT_FOUND';
    public const TRANSFER_IDENTITY_NOT_SUPPORTED = 'PAYMENT__TRANSFER_IDENTITY_NOT_SUPPORTED';
    public const TRANSFER_RECIPIENT_INCOMPLETE = 'PAYMENT__TRANSFER_RECIPIENT_INCOMPLETE';
    public const TRANSFER_RECIPIENT_NOT_READY = 'PAYMENT__TRANSFER_RECIPIENT_NOT_READY';
    public const TRANSFER_IDENTITY_VALUE_TOO_LONG = 'PAYMENT__TRANSFER_IDENTITY_VALUE_TOO_LONG';
    public const TRANSFER_AMOUNT_INVALID = 'PAYMENT__TRANSFER_AMOUNT_INVALID';
    public const TRANSFER_OUT_NO_INVALID = 'PAYMENT__TRANSFER_OUT_NO_INVALID';
    public const TRANSFER_OUT_NO_TAKEN = 'PAYMENT__TRANSFER_OUT_NO_TAKEN';
    public const TRANSFER_ALREADY_FINALIZED = 'PAYMENT__TRANSFER_ALREADY_FINALIZED';
    public const TRANSFER_FOREIGN_APP = 'PAYMENT__TRANSFER_FOREIGN_APP';
    public const TRANSFER_PROVIDER_REF_MISSING = 'PAYMENT__TRANSFER_PROVIDER_REF_MISSING';
    public const TRANSFER_NOTIFICATION_APP_UNKNOWN = 'PAYMENT__TRANSFER_NOTIFICATION_APP_UNKNOWN';
    public const TRANSFER_NOTIFICATION_PROVIDER_UNKNOWN = 'PAYMENT__TRANSFER_NOTIFICATION_PROVIDER_UNKNOWN';
    public const TRANSFER_NOTIFICATION_TRANSFER_UNKNOWN = 'PAYMENT__TRANSFER_NOTIFICATION_TRANSFER_UNKNOWN';

    /**
     * @deprecated tag:v6.8.0 - Use PaymentProviderException::unknownProvider() instead
     *
     * @phpstan-ignore shopwell.deprecatedMethod (the forwarding facade must keep existing payment calls usable during the migration)
     */
    public static function unknownProvider(string $technicalName): self
    {
        return PaymentProviderException::unknownProvider($technicalName);
    }

    /**
     * @deprecated tag:v6.8.0 - Use PaymentProviderException::unknownChannel() instead
     *
     * @phpstan-ignore shopwell.deprecatedMethod (the forwarding facade must keep existing payment calls usable during the migration)
     */
    public static function unknownChannel(string $technicalName): self
    {
        return PaymentProviderException::unknownChannel($technicalName);
    }

    /**
     * @deprecated tag:v6.8.0 - Use PaymentProviderException::duplicateChannel() instead
     *
     * @phpstan-ignore shopwell.deprecatedMethod (the forwarding facade must keep existing payment calls usable during the migration)
     */
    public static function duplicateChannel(string $channel, string $firstProvider, string $secondProvider): self
    {
        return PaymentProviderException::duplicateChannel($channel, $firstProvider, $secondProvider);
    }

    /**
     * @deprecated tag:v6.8.0 - Use PaymentProviderException::invalidProviderDeclaration() instead
     *
     * @phpstan-ignore shopwell.deprecatedMethod (the forwarding facade must keep existing payment calls usable during the migration)
     */
    public static function invalidProviderDeclaration(string $reason): self
    {
        return PaymentProviderException::invalidProviderDeclaration($reason);
    }

    /**
     * @deprecated tag:v6.8.0 - Use PaymentProviderException::duplicateProvider() instead
     *
     * @phpstan-ignore shopwell.deprecatedMethod (the forwarding facade must keep existing payment calls usable during the migration)
     */
    public static function duplicateProvider(string $technicalName, string $firstDeclaration, string $secondDeclaration): self
    {
        return PaymentProviderException::duplicateProvider($technicalName, $firstDeclaration, $secondDeclaration);
    }

    /**
     * @deprecated tag:v6.8.0 - Use PaymentProviderException::ambiguousProviderPriority() instead
     *
     * @phpstan-ignore shopwell.deprecatedMethod (the forwarding facade must keep existing payment calls usable during the migration)
     */
    public static function ambiguousProviderPriority(
        string $technicalName,
        int $priority,
        string $firstDeclaration,
        string $secondDeclaration,
    ): self {
        return PaymentProviderException::ambiguousProviderPriority($technicalName, $priority, $firstDeclaration, $secondDeclaration);
    }

    /**
     * @deprecated tag:v6.8.0 - Use PaymentProviderException::invalidProviderName() instead
     *
     * @phpstan-ignore shopwell.deprecatedMethod (the forwarding facade must keep existing payment calls usable during the migration)
     */
    public static function invalidProviderName(string $technicalName): self
    {
        return PaymentProviderException::invalidProviderName($technicalName);
    }

    public static function stateMachineInvalid(string $stateMachine, string $reason): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::STATE_MACHINE_INVALID,
            'The state machine "{{ stateMachine }}" is not usable for payments: {{ reason }}',
            ['stateMachine' => $stateMachine, 'reason' => $reason]
        );
    }

    /**
     * @deprecated tag:v6.8.0 - Use PaymentProviderException::providerNotEnabled() instead
     *
     * @phpstan-ignore shopwell.deprecatedMethod (the forwarding facade must keep existing payment calls usable during the migration)
     */
    public static function providerNotEnabled(string $technicalName, string $appId): self
    {
        return PaymentProviderException::providerNotEnabled($technicalName, $appId);
    }

    /**
     * @deprecated tag:v6.8.0 - Use PaymentProviderException::providerNotConfigured() instead
     *
     * @phpstan-ignore shopwell.deprecatedMethod (the forwarding facade must keep existing payment calls usable during the migration)
     */
    public static function providerNotConfigured(string $technicalName): self
    {
        return PaymentProviderException::providerNotConfigured($technicalName);
    }

    /**
     * @deprecated tag:v6.8.0 - Use PaymentProviderException::providerInheritanceCycle() instead
     *
     * @phpstan-ignore shopwell.deprecatedMethod (the forwarding facade must keep existing payment calls usable during the migration)
     */
    public static function providerInheritanceCycle(string $technicalName, string $appTechnicalName): self
    {
        return PaymentProviderException::providerInheritanceCycle($technicalName, $appTechnicalName);
    }

    /**
     * @deprecated tag:v6.8.0 - Use PaymentProviderException::providerSourceAppMissing() instead
     *
     * @phpstan-ignore shopwell.deprecatedMethod (the forwarding facade must keep existing payment calls usable during the migration)
     */
    public static function providerSourceAppMissing(string $technicalName, string $appTechnicalName, string $sourceAppId): self
    {
        return PaymentProviderException::providerSourceAppMissing($technicalName, $appTechnicalName, $sourceAppId);
    }

    /**
     * @deprecated tag:v6.8.0 - Use PaymentProviderException::channelNotEnabled() instead
     *
     * @phpstan-ignore shopwell.deprecatedMethod (the forwarding facade must keep existing payment calls usable during the migration)
     */
    public static function channelNotEnabled(string $technicalName, string $appId): self
    {
        return PaymentProviderException::channelNotEnabled($technicalName, $appId);
    }

    /**
     * @deprecated tag:v6.8.0 - Use PaymentProviderException::channelNotConfigured() instead
     *
     * @phpstan-ignore shopwell.deprecatedMethod (the forwarding facade must keep existing payment calls usable during the migration)
     */
    public static function channelNotConfigured(string $technicalName, string $field): self
    {
        return PaymentProviderException::channelNotConfigured($technicalName, $field);
    }

    /**
     * @deprecated tag:v6.8.0 - Use PaymentProviderException::channelDisabled() instead
     *
     * @phpstan-ignore shopwell.deprecatedMethod (the forwarding facade must keep existing payment calls usable during the migration)
     */
    public static function channelDisabled(string $technicalName): self
    {
        return PaymentProviderException::channelDisabled($technicalName);
    }

    /**
     * @deprecated tag:v6.8.0 - Use PaymentProviderException::channelNotSupported() instead
     *
     * @phpstan-ignore shopwell.deprecatedMethod (the forwarding facade must keep existing payment calls usable during the migration)
     */
    public static function channelNotSupported(string $channel, string $provider): self
    {
        return PaymentProviderException::channelNotSupported($channel, $provider);
    }

    /**
     * @deprecated tag:v6.8.0 - Use PaymentProviderException::currencyNotSupported() instead
     *
     * @phpstan-ignore shopwell.deprecatedMethod (the forwarding facade must keep existing payment calls usable during the migration)
     */
    public static function currencyNotSupported(string $technicalName, string $currency): self
    {
        return PaymentProviderException::currencyNotSupported($technicalName, $currency);
    }

    /**
     * @deprecated tag:v6.8.0 - Use PaymentProviderException::deviceNotSupported() instead
     *
     * @phpstan-ignore shopwell.deprecatedMethod (the forwarding facade must keep existing payment calls usable during the migration)
     */
    public static function deviceNotSupported(string $technicalName, string $device): self
    {
        return PaymentProviderException::deviceNotSupported($technicalName, $device);
    }

    /**
     * @param list<string> $missing
     *
     * @deprecated tag:v6.8.0 - Use PaymentProviderException::missingRuntimeParams() instead
     *
     * @phpstan-ignore shopwell.deprecatedMethod (the forwarding facade must keep existing payment calls usable during the migration)
     */
    public static function missingRuntimeParams(string $technicalName, array $missing): self
    {
        return PaymentProviderException::missingRuntimeParams($technicalName, $missing);
    }

    /**
     * @param list<string> $missing
     *
     * @deprecated tag:v6.8.0 - Use PaymentAppException::missingAuthParameters() instead
     *
     * @phpstan-ignore shopwell.deprecatedMethod (the forwarding facade must keep existing payment calls usable during the migration)
     */
    public static function missingAuthParameters(array $missing): self
    {
        return PaymentAppException::missingAuthParameters($missing);
    }

    /**
     * @deprecated tag:v6.8.0 - Use PaymentAppException::invalidSignature() instead
     *
     * @phpstan-ignore shopwell.deprecatedMethod (the forwarding facade must keep existing payment calls usable during the migration)
     */
    public static function invalidSignature(string $appId): self
    {
        return PaymentAppException::invalidSignature($appId);
    }

    /**
     * @deprecated tag:v6.8.0 - Use PaymentAppException::expiredTimestamp() instead
     *
     * @phpstan-ignore shopwell.deprecatedMethod (the forwarding facade must keep existing payment calls usable during the migration)
     */
    public static function expiredTimestamp(int $timestamp, int $window): self
    {
        return PaymentAppException::expiredTimestamp($timestamp, $window);
    }

    /**
     * @deprecated tag:v6.8.0 - Use PaymentAppException::foreignApp() instead
     *
     * @phpstan-ignore shopwell.deprecatedMethod (the forwarding facade must keep existing payment calls usable during the migration)
     */
    public static function foreignApp(string $appId, string $paymentNo): self
    {
        return PaymentAppException::foreignApp($appId, $paymentNo);
    }

    /**
     * @deprecated tag:v6.8.0 - Use PaymentAppException::appNotFound() instead
     *
     * @phpstan-ignore shopwell.deprecatedMethod (the forwarding facade must keep existing payment calls usable during the migration)
     */
    public static function appNotFound(string $technicalName): self
    {
        return PaymentAppException::appNotFound($technicalName);
    }

    /**
     * @deprecated tag:v6.8.0 - Use PaymentAppException::appDisabled() instead
     *
     * @phpstan-ignore shopwell.deprecatedMethod (the forwarding facade must keep existing payment calls usable during the migration)
     */
    public static function appDisabled(string $technicalName): self
    {
        return PaymentAppException::appDisabled($technicalName);
    }

    /**
     * @deprecated tag:v6.8.0 - Use PaymentAppException::platformTenantMissing() instead
     *
     * @phpstan-ignore shopwell.deprecatedMethod (the forwarding facade must keep existing payment calls usable during the migration)
     */
    public static function platformTenantMissing(string $technicalName): self
    {
        return PaymentAppException::platformTenantMissing($technicalName);
    }

    public static function missingParameter(string $name): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::MISSING_PARAMETER,
            'The parameter "{{ name }}" is required.',
            ['name' => $name]
        );
    }

    /**
     * @deprecated tag:v6.8.0 - Use PaymentOrderException::orderNotFound() instead
     *
     * @phpstan-ignore shopwell.deprecatedMethod (the forwarding facade must keep existing payment calls usable during the migration)
     */
    public static function orderNotFound(string $paymentNo): self
    {
        return PaymentOrderException::orderNotFound($paymentNo);
    }

    /**
     * @deprecated tag:v6.8.0 - Use PaymentOrderException::orderConflict() instead
     *
     * @phpstan-ignore shopwell.deprecatedMethod (the forwarding facade must keep existing payment calls usable during the migration)
     */
    public static function orderConflict(string $outTradeNo): self
    {
        return PaymentOrderException::orderConflict($outTradeNo);
    }

    /**
     * @deprecated tag:v6.8.0 - Use PaymentOrderException::orderNotPayable() instead
     *
     * @phpstan-ignore shopwell.deprecatedMethod (the forwarding facade must keep existing payment calls usable during the migration)
     */
    public static function orderNotPayable(string $paymentNo): self
    {
        return PaymentOrderException::orderNotPayable($paymentNo);
    }

    /**
     * @deprecated tag:v6.8.0 - Use PaymentOrderException::orderAlreadyClosed() instead
     *
     * @phpstan-ignore shopwell.deprecatedMethod (the forwarding facade must keep existing payment calls usable during the migration)
     */
    public static function orderAlreadyClosed(string $paymentNo): self
    {
        return PaymentOrderException::orderAlreadyClosed($paymentNo);
    }

    /**
     * @deprecated tag:v6.8.0 - Use PaymentOrderException::orderAlreadyPaid() instead
     *
     * @phpstan-ignore shopwell.deprecatedMethod (the forwarding facade must keep existing payment calls usable during the migration)
     */
    public static function orderAlreadyPaid(string $paymentNo): self
    {
        return PaymentOrderException::orderAlreadyPaid($paymentNo);
    }

    /**
     * @deprecated tag:v6.8.0 - Use PaymentOrderException::orderNotExpirable() instead
     *
     * @phpstan-ignore shopwell.deprecatedMethod (the forwarding facade must keep existing payment calls usable during the migration)
     */
    public static function orderNotExpirable(string $paymentNo): self
    {
        return PaymentOrderException::orderNotExpirable($paymentNo);
    }

    /**
     * @deprecated tag:v6.8.0 - Use PaymentOrderException::invalidAmount() instead
     *
     * @phpstan-ignore shopwell.deprecatedMethod (the forwarding facade must keep existing payment calls usable during the migration)
     */
    public static function invalidAmount(int $amount): self
    {
        return PaymentOrderException::invalidAmount($amount);
    }

    /**
     * @deprecated tag:v6.8.0 - Use PaymentOrderException::amountMismatch() instead
     *
     * @phpstan-ignore shopwell.deprecatedMethod (the forwarding facade must keep existing payment calls usable during the migration)
     */
    public static function amountMismatch(string $paymentNo, int $expected, int $actual): self
    {
        return PaymentOrderException::amountMismatch($paymentNo, $expected, $actual);
    }

    /**
     * @deprecated tag:v6.8.0 - Use PaymentOrderException::currencyMismatch() instead
     *
     * @phpstan-ignore shopwell.deprecatedMethod (the forwarding facade must keep existing payment calls usable during the migration)
     */
    public static function currencyMismatch(string $paymentNo, string $expected, string $actual): self
    {
        return PaymentOrderException::currencyMismatch($paymentNo, $expected, $actual);
    }

    /**
     * @deprecated tag:v6.8.0 - Use PaymentOrderException::refundNotAllowed() instead
     *
     * @phpstan-ignore shopwell.deprecatedMethod (the forwarding facade must keep existing payment calls usable during the migration)
     */
    public static function refundNotAllowed(string $paymentNo): self
    {
        return PaymentOrderException::refundNotAllowed($paymentNo);
    }

    /**
     * @deprecated tag:v6.8.0 - Use PaymentOrderException::refundAmountExceedsOrder() instead
     *
     * @phpstan-ignore shopwell.deprecatedMethod (the forwarding facade must keep existing payment calls usable during the migration)
     */
    public static function refundAmountExceedsOrder(string $paymentNo, int $amount, int $available): self
    {
        return PaymentOrderException::refundAmountExceedsOrder($paymentNo, $amount, $available);
    }

    /**
     * @deprecated tag:v6.8.0 - Use PaymentNotificationException::notificationInvalid() instead
     *
     * @phpstan-ignore shopwell.deprecatedMethod (the forwarding facade must keep existing payment calls usable during the migration)
     */
    public static function notificationInvalid(string $channel, string $reason): self
    {
        return PaymentNotificationException::notificationInvalid($channel, $reason);
    }

    /**
     * @deprecated tag:v6.8.0 - Use PaymentNotificationException::notificationOrderUnknown() instead
     *
     * @phpstan-ignore shopwell.deprecatedMethod (the forwarding facade must keep existing payment calls usable during the migration)
     */
    public static function notificationOrderUnknown(string $channel, string $reference): self
    {
        return PaymentNotificationException::notificationOrderUnknown($channel, $reference);
    }

    /**
     * @deprecated tag:v6.8.0 - Use PaymentNotificationException::notificationNotFound() instead
     *
     * @phpstan-ignore shopwell.deprecatedMethod (the forwarding facade must keep existing payment calls usable during the migration)
     */
    public static function notificationNotFound(string $id): self
    {
        return PaymentNotificationException::notificationNotFound($id);
    }

    /**
     * @deprecated tag:v6.8.0 - Use PaymentNotificationException::webhookDeliveryNotFound() instead
     *
     * @phpstan-ignore shopwell.deprecatedMethod (the forwarding facade must keep existing payment calls usable during the migration)
     */
    public static function webhookDeliveryNotFound(string $id): self
    {
        return PaymentNotificationException::webhookDeliveryNotFound($id);
    }

    /**
     * The interface failed. The original exception is kept as the previous one: the message alone
     * loses the stack and the raw answer of the channel, which is what a failure is debugged with.
     */
    public static function gatewayError(string $channel, string $reason, ?\Throwable $previous = null): self
    {
        return new self(
            Response::HTTP_BAD_GATEWAY,
            self::GATEWAY_ERROR,
            'The payment channel "{{ channel }}" returned an error: {{ reason }}',
            ['channel' => $channel, 'reason' => $reason],
            $previous
        );
    }

    public static function missingCredentials(string $channel, string $field): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::MISSING_CREDENTIALS,
            'The payment channel "{{ channel }}" is missing the credential "{{ field }}".',
            ['channel' => $channel, 'field' => $field]
        );
    }

    /**
     * @deprecated tag:v6.8.0 - Use PaymentGatewayException::gatewayResultMissing() instead
     *
     * @phpstan-ignore shopwell.deprecatedMethod (the forwarding facade must keep existing payment calls usable during the migration)
     */
    public static function gatewayResultMissing(string $channel): self
    {
        return PaymentGatewayException::gatewayResultMissing($channel);
    }

    /**
     * @deprecated tag:v6.8.0 - Use PaymentOrderException::refundNotFound() instead
     *
     * @phpstan-ignore shopwell.deprecatedMethod (the forwarding facade must keep existing payment calls usable during the migration)
     */
    public static function refundNotFound(string $refundNo): self
    {
        return PaymentOrderException::refundNotFound($refundNo);
    }

    /**
     * @deprecated tag:v6.8.0 - Use PaymentGatewayException::unsupportedApi() instead
     *
     * @phpstan-ignore shopwell.deprecatedMethod (the forwarding facade must keep existing payment calls usable during the migration)
     */
    public static function unsupportedApi(string $action, string $api): self
    {
        return PaymentGatewayException::unsupportedApi($action, $api);
    }

    /**
     * @deprecated tag:v6.8.0 - Use PaymentGatewayException::actionWithoutApi() instead
     *
     * @phpstan-ignore shopwell.deprecatedMethod (the forwarding facade must keep existing payment calls usable during the migration)
     */
    public static function actionWithoutApi(string $action): self
    {
        return PaymentGatewayException::actionWithoutApi($action);
    }

    /**
     * The caller asked for an operation the interface never declared.
     *
     * Payum answers an unsupported request with an error inside the gateway, which the services
     * around it can only report as a channel fault. That reads as "the interface is down" to whoever
     * called, when the truth is "this interface cannot do that at all" - a fact the declaration knows
     * before a single request is signed, and the one answer the caller can act on.
     */
    public static function providerLacksCapability(string $technicalName, string $capability): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::PROVIDER_LACKS_CAPABILITY,
            'The payment provider "{{ technicalName }}" does not support "{{ capability }}".',
            ['technicalName' => $technicalName, 'capability' => $capability]
        );
    }

    /**
     * @deprecated tag:v6.8.0 - Use PaymentCheckoutBindingException::paymentMethodChannelMissing() instead
     *
     * @phpstan-ignore shopwell.deprecatedMethod (the forwarding facade must keep existing payment calls usable during the migration)
     */
    public static function paymentMethodChannelMissing(string $paymentMethodId): self
    {
        return PaymentCheckoutBindingException::paymentMethodChannelMissing($paymentMethodId);
    }

    /**
     * @deprecated tag:v6.8.0 - Use PaymentCheckoutBindingException::paymentMethodChannelTaken() instead
     *
     * @phpstan-ignore shopwell.deprecatedMethod (the forwarding facade must keep existing payment calls usable during the migration)
     */
    public static function paymentMethodChannelTaken(string $paymentAppName, string $channelTechnicalName, string $paymentMethodId, string $paymentMethodName): self
    {
        return PaymentCheckoutBindingException::paymentMethodChannelTaken($paymentAppName, $channelTechnicalName, $paymentMethodId, $paymentMethodName);
    }

    /**
     * @deprecated tag:v6.8.0 - Use PaymentAppException::platformTenantNotDeletable() instead
     *
     * @phpstan-ignore shopwell.deprecatedMethod (the forwarding facade must keep existing payment calls usable during the migration)
     */
    public static function platformTenantNotDeletable(string $technicalName): self
    {
        return PaymentAppException::platformTenantNotDeletable($technicalName);
    }

    /**
     * @deprecated tag:v6.8.0 - Use PaymentCheckoutBindingException::channelNotAvailableInStorefront() instead
     *
     * @phpstan-ignore shopwell.deprecatedMethod (the forwarding facade must keep existing payment calls usable during the migration)
     */
    public static function channelNotAvailableInStorefront(string $technicalName): self
    {
        return PaymentCheckoutBindingException::channelNotAvailableInStorefront($technicalName);
    }

    /**
     * @deprecated tag:v6.8.0 - Use PaymentSubscriptionException::paymentCycleNotExpressible() instead
     *
     * @phpstan-ignore shopwell.deprecatedMethod (the forwarding facade must keep existing payment calls usable during the migration)
     */
    public static function paymentCycleNotExpressible(string $provider, string $unit, int $count, int $anchorDayOfMonth): self
    {
        return PaymentSubscriptionException::paymentCycleNotExpressible($provider, $unit, $count, $anchorDayOfMonth);
    }

    /**
     * @deprecated tag:v6.8.0 - Use PaymentSubscriptionException::paymentCycleNotFound() instead
     *
     * @phpstan-ignore shopwell.deprecatedMethod (the forwarding facade must keep existing payment calls usable during the migration)
     */
    public static function paymentCycleNotFound(string $id): self
    {
        return PaymentSubscriptionException::paymentCycleNotFound($id);
    }

    /**
     * @deprecated tag:v6.8.0 - Use PaymentSubscriptionException::paymentCycleConflict() instead
     *
     * @phpstan-ignore shopwell.deprecatedMethod (the forwarding facade must keep existing payment calls usable during the migration)
     */
    public static function paymentCycleConflict(string $subscriptionId, int $cycleNo): self
    {
        return PaymentSubscriptionException::paymentCycleConflict($subscriptionId, $cycleNo);
    }

    /**
     * @deprecated tag:v6.8.0 - Use PaymentSubscriptionException::paymentCycleNotRearmable() instead
     *
     * @phpstan-ignore shopwell.deprecatedMethod (the forwarding facade must keep existing payment calls usable during the migration)
     */
    public static function paymentCycleNotRearmable(string $id, string $state): self
    {
        return PaymentSubscriptionException::paymentCycleNotRearmable($id, $state);
    }

    /**
     * @deprecated tag:v6.8.0 - Use PaymentSubscriptionException::subscriptionNotFound() instead
     *
     * @phpstan-ignore shopwell.deprecatedMethod (the forwarding facade must keep existing payment calls usable during the migration)
     */
    public static function subscriptionNotFound(string $id): self
    {
        return PaymentSubscriptionException::subscriptionNotFound($id);
    }

    /**
     * @deprecated tag:v6.8.0 - Use PaymentSubscriptionException::invalidPeriodUnit() instead
     *
     * @phpstan-ignore shopwell.deprecatedMethod (the forwarding facade must keep existing payment calls usable during the migration)
     */
    public static function invalidPeriodUnit(string $unit): self
    {
        return PaymentSubscriptionException::invalidPeriodUnit($unit);
    }

    /**
     * @deprecated tag:v6.8.0 - Use PaymentSubscriptionException::invalidPeriodCount() instead
     *
     * @phpstan-ignore shopwell.deprecatedMethod (the forwarding facade must keep existing payment calls usable during the migration)
     */
    public static function invalidPeriodCount(int $count): self
    {
        return PaymentSubscriptionException::invalidPeriodCount($count);
    }

    /**
     * @deprecated tag:v6.8.0 - Use PaymentTransferException::transferNotFound() instead
     *
     * @phpstan-ignore shopwell.deprecatedMethod (the forwarding facade must keep existing payment calls usable during the migration)
     */
    public static function transferNotFound(string $id): self
    {
        return PaymentTransferException::transferNotFound($id);
    }

    /**
     * @deprecated tag:v6.8.0 - Use PaymentTransferException::transferConflict() instead
     *
     * @phpstan-ignore shopwell.deprecatedMethod (the forwarding facade must keep existing payment calls usable during the migration)
     */
    public static function transferConflict(string $reference): self
    {
        return PaymentTransferException::transferConflict($reference);
    }

    /**
     * @deprecated tag:v6.8.0 - Use PaymentTransferException::transferRecipientNotFound() instead
     *
     * @phpstan-ignore shopwell.deprecatedMethod (the forwarding facade must keep existing payment calls usable during the migration)
     */
    public static function transferRecipientNotFound(string $id): self
    {
        return PaymentTransferException::transferRecipientNotFound($id);
    }

    /**
     * @deprecated tag:v6.8.0 - Use PaymentTransferException::transferIdentityNotSupported() instead
     *
     * @phpstan-ignore shopwell.deprecatedMethod (the forwarding facade must keep existing payment calls usable during the migration)
     */
    public static function transferIdentityNotSupported(string $provider, string $identityType): self
    {
        return PaymentTransferException::transferIdentityNotSupported($provider, $identityType);
    }

    /**
     * @deprecated tag:v6.8.0 - Use PaymentTransferException::transferRecipientIncomplete() instead
     *
     * @phpstan-ignore shopwell.deprecatedMethod (the forwarding facade must keep existing payment calls usable during the migration)
     */
    public static function transferRecipientIncomplete(string $field): self
    {
        return PaymentTransferException::transferRecipientIncomplete($field);
    }

    /**
     * @deprecated tag:v6.8.0 - Use PaymentTransferException::transferIdentityValueTooLong() instead
     *
     * @phpstan-ignore shopwell.deprecatedMethod (the forwarding facade must keep existing payment calls usable during the migration)
     */
    public static function transferIdentityValueTooLong(int $length): self
    {
        return PaymentTransferException::transferIdentityValueTooLong($length);
    }

    /**
     * @deprecated tag:v6.8.0 - Use PaymentTransferException::transferAmountInvalid() instead
     *
     * @phpstan-ignore shopwell.deprecatedMethod (the forwarding facade must keep existing payment calls usable during the migration)
     */
    public static function transferAmountInvalid(): self
    {
        return PaymentTransferException::transferAmountInvalid();
    }

    /**
     * @deprecated tag:v6.8.0 - Use PaymentTransferException::transferOutNoInvalid() instead
     *
     * @phpstan-ignore shopwell.deprecatedMethod (the forwarding facade must keep existing payment calls usable during the migration)
     */
    public static function transferOutNoInvalid(string $outNo): self
    {
        return PaymentTransferException::transferOutNoInvalid($outNo);
    }

    /**
     * @deprecated tag:v6.8.0 - Use PaymentTransferException::transferOutNoTaken() instead
     *
     * @phpstan-ignore shopwell.deprecatedMethod (the forwarding facade must keep existing payment calls usable during the migration)
     */
    public static function transferOutNoTaken(string $outNo): self
    {
        return PaymentTransferException::transferOutNoTaken($outNo);
    }

    /**
     * @deprecated tag:v6.8.0 - Use PaymentTransferException::transferAppNotAvailable() instead
     *
     * @phpstan-ignore shopwell.deprecatedMethod (the forwarding facade must keep existing payment calls usable during the migration)
     */
    public static function transferAppNotAvailable(string $appId): self
    {
        return PaymentTransferException::transferAppNotAvailable($appId);
    }

    /**
     * @deprecated tag:v6.8.0 - Use PaymentTransferException::transferAlreadyFinalized() instead
     *
     * @phpstan-ignore shopwell.deprecatedMethod (the forwarding facade must keep existing payment calls usable during the migration)
     */
    public static function transferAlreadyFinalized(string $outNo, string $state): self
    {
        return PaymentTransferException::transferAlreadyFinalized($outNo, $state);
    }

    /**
     * @deprecated tag:v6.8.0 - Use PaymentTransferException::transferForeignApp() instead
     *
     * @phpstan-ignore shopwell.deprecatedMethod (the forwarding facade must keep existing payment calls usable during the migration)
     */
    public static function transferForeignApp(string $appId, string $outNo): self
    {
        return PaymentTransferException::transferForeignApp($appId, $outNo);
    }

    /**
     * @deprecated tag:v6.8.0 - Use PaymentTransferException::transferProviderRefMissing() instead
     *
     * @phpstan-ignore shopwell.deprecatedMethod (the forwarding facade must keep existing payment calls usable during the migration)
     */
    public static function transferProviderRefMissing(string $outNo): self
    {
        return PaymentTransferException::transferProviderRefMissing($outNo);
    }

    /**
     * @deprecated tag:v6.8.0 - Use PaymentTransferException::transferRecipientNotReady() instead
     *
     * @phpstan-ignore shopwell.deprecatedMethod (the forwarding facade must keep existing payment calls usable during the migration)
     */
    public static function transferRecipientNotReady(string $recipientId): self
    {
        return PaymentTransferException::transferRecipientNotReady($recipientId);
    }

    /**
     * @deprecated tag:v6.8.0 - Use PaymentTransferException::transferNotificationAppUnknown() instead
     *
     * @phpstan-ignore shopwell.deprecatedMethod (the forwarding facade must keep existing payment calls usable during the migration)
     */
    public static function transferNotificationAppUnknown(string $appId): self
    {
        return PaymentTransferException::transferNotificationAppUnknown($appId);
    }

    /**
     * @deprecated tag:v6.8.0 - Use PaymentTransferException::transferNotificationProviderUnknown() instead
     *
     * @phpstan-ignore shopwell.deprecatedMethod (the forwarding facade must keep existing payment calls usable during the migration)
     */
    public static function transferNotificationProviderUnknown(string $provider): self
    {
        return PaymentTransferException::transferNotificationProviderUnknown($provider);
    }

    /**
     * @deprecated tag:v6.8.0 - Use PaymentTransferException::transferNotificationTransferUnknown() instead
     *
     * @phpstan-ignore shopwell.deprecatedMethod (the forwarding facade must keep existing payment calls usable during the migration)
     */
    public static function transferNotificationTransferUnknown(string $outNo): self
    {
        return PaymentTransferException::transferNotificationTransferUnknown($outNo);
    }
}
