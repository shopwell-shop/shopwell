<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment;

use Shopwell\Core\Framework\HttpException;
use Shopwell\Core\Framework\Log\Package;
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
    public const INVALID_PROVIDER_DECLARATION = 'PAYMENT__INVALID_PROVIDER_DECLARATION';

    public const PROVIDER_NOT_ENABLED = 'PAYMENT__PROVIDER_NOT_ENABLED';
    public const PROVIDER_NOT_CONFIGURED = 'PAYMENT__PROVIDER_NOT_CONFIGURED';
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

    public const MISSING_PARAMETER = 'PAYMENT__MISSING_PARAMETER';

    public const ORDER_NOT_FOUND = 'PAYMENT__ORDER_NOT_FOUND';
    public const ORDER_NOT_PAYABLE = 'PAYMENT__ORDER_NOT_PAYABLE';
    public const ORDER_ALREADY_CLOSED = 'PAYMENT__ORDER_ALREADY_CLOSED';
    public const INVALID_AMOUNT = 'PAYMENT__INVALID_AMOUNT';
    public const AMOUNT_MISMATCH = 'PAYMENT__AMOUNT_MISMATCH';

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

    public static function unknownProvider(string $technicalName): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::UNKNOWN_PROVIDER,
            'The payment provider "{{ technicalName }}" is unknown.',
            ['technicalName' => $technicalName]
        );
    }

    public static function unknownChannel(string $technicalName): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::UNKNOWN_CHANNEL,
            'The payment channel "{{ technicalName }}" is unknown.',
            ['technicalName' => $technicalName]
        );
    }

    public static function duplicateChannel(string $channel, string $firstProvider, string $secondProvider): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::DUPLICATE_CHANNEL,
            'The payment channel "{{ channel }}" is declared by the providers "{{ firstProvider }}" and "{{ secondProvider }}".',
            ['channel' => $channel, 'firstProvider' => $firstProvider, 'secondProvider' => $secondProvider]
        );
    }

    public static function invalidProviderDeclaration(string $reason): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::INVALID_PROVIDER_DECLARATION,
            'A payment provider declaration is invalid: {{ reason }}',
            ['reason' => $reason]
        );
    }

    public static function providerNotEnabled(string $technicalName, string $appId): self
    {
        return new self(
            Response::HTTP_FORBIDDEN,
            self::PROVIDER_NOT_ENABLED,
            'The payment provider "{{ technicalName }}" is not enabled for the app "{{ appId }}".',
            ['technicalName' => $technicalName, 'appId' => $appId]
        );
    }

    public static function providerNotConfigured(string $technicalName): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::PROVIDER_NOT_CONFIGURED,
            'The payment provider "{{ technicalName }}" is enabled but its credentials are not configured.',
            ['technicalName' => $technicalName]
        );
    }

    public static function channelNotEnabled(string $technicalName, string $appId): self
    {
        return new self(
            Response::HTTP_FORBIDDEN,
            self::CHANNEL_NOT_ENABLED,
            'The payment channel "{{ technicalName }}" is not enabled for the app "{{ appId }}".',
            ['technicalName' => $technicalName, 'appId' => $appId]
        );
    }

    public static function channelNotConfigured(string $technicalName, string $field): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::CHANNEL_NOT_CONFIGURED,
            'The payment channel "{{ technicalName }}" is missing the parameter "{{ field }}".',
            ['technicalName' => $technicalName, 'field' => $field]
        );
    }

    /**
     * The platform does not offer this channel at all - no app can collect through it, whether it pays
     * with the credentials of the platform or with its own ones.
     */
    public static function channelDisabled(string $technicalName): self
    {
        return new self(
            Response::HTTP_FORBIDDEN,
            self::CHANNEL_DISABLED,
            'The payment channel "{{ technicalName }}" is not offered by the platform.',
            ['technicalName' => $technicalName]
        );
    }

    /**
     * A channel of an interface that the adapter of that interface cannot open - the declaration and
     * the adapter drifted apart, which is a programming error rather than a problem of the caller.
     */
    public static function channelNotSupported(string $channel, string $provider): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::CHANNEL_NOT_SUPPORTED,
            'The payment interface "{{ provider }}" cannot open a payment with the channel "{{ channel }}".',
            ['channel' => $channel, 'provider' => $provider]
        );
    }

    public static function currencyNotSupported(string $technicalName, string $currency): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::CURRENCY_NOT_SUPPORTED,
            'The payment channel "{{ technicalName }}" does not support the currency "{{ currency }}".',
            ['technicalName' => $technicalName, 'currency' => $currency]
        );
    }

    public static function deviceNotSupported(string $technicalName, string $device): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::DEVICE_NOT_SUPPORTED,
            'The payment channel "{{ technicalName }}" cannot be used on the device "{{ device }}".',
            ['technicalName' => $technicalName, 'device' => $device]
        );
    }

    /**
     * @param list<string> $missing
     */
    public static function missingRuntimeParams(string $technicalName, array $missing): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::MISSING_RUNTIME_PARAMS,
            'The payment channel "{{ technicalName }}" requires the parameter(s): {{ missing }}',
            ['technicalName' => $technicalName, 'missing' => implode(', ', $missing)]
        );
    }

    /**
     * @param list<string> $missing
     */
    public static function missingAuthParameters(array $missing): self
    {
        return new self(
            Response::HTTP_UNAUTHORIZED,
            self::MISSING_AUTH_PARAMETERS,
            'Missing authentication parameter(s): {{ missing }}',
            ['missing' => implode(', ', $missing)]
        );
    }

    public static function invalidSignature(string $appId): self
    {
        return new self(
            Response::HTTP_UNAUTHORIZED,
            self::INVALID_SIGNATURE,
            'The request signature of the app "{{ appId }}" is invalid.',
            ['appId' => $appId]
        );
    }

    public static function expiredTimestamp(int $timestamp, int $window): self
    {
        return new self(
            Response::HTTP_UNAUTHORIZED,
            self::EXPIRED_TIMESTAMP,
            'The request timestamp "{{ timestamp }}" is outside of the allowed window of {{ window }} seconds.',
            ['timestamp' => $timestamp, 'window' => $window]
        );
    }

    public static function foreignApp(string $appId, string $paymentNo): self
    {
        return new self(
            Response::HTTP_FORBIDDEN,
            self::FOREIGN_APP,
            'The payment order "{{ paymentNo }}" does not belong to the app "{{ appId }}".',
            ['appId' => $appId, 'paymentNo' => $paymentNo]
        );
    }

    public static function appNotFound(string $technicalName): self
    {
        return new self(
            Response::HTTP_UNAUTHORIZED,
            self::APP_NOT_FOUND,
            'The payment app "{{ technicalName }}" is unknown.',
            ['technicalName' => $technicalName]
        );
    }

    public static function appDisabled(string $technicalName): self
    {
        return new self(
            Response::HTTP_FORBIDDEN,
            self::APP_DISABLED,
            'The payment app "{{ technicalName }}" is disabled.',
            ['technicalName' => $technicalName]
        );
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

    public static function orderNotFound(string $paymentNo): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::ORDER_NOT_FOUND,
            'The payment order "{{ paymentNo }}" does not exist.',
            ['paymentNo' => $paymentNo]
        );
    }

    public static function orderNotPayable(string $paymentNo): self
    {
        return new self(
            Response::HTTP_CONFLICT,
            self::ORDER_NOT_PAYABLE,
            'The payment order "{{ paymentNo }}" is not open for payment anymore.',
            ['paymentNo' => $paymentNo]
        );
    }

    public static function orderAlreadyClosed(string $paymentNo): self
    {
        return new self(
            Response::HTTP_CONFLICT,
            self::ORDER_ALREADY_CLOSED,
            'The payment order "{{ paymentNo }}" is already closed.',
            ['paymentNo' => $paymentNo]
        );
    }

    public static function invalidAmount(int $amount): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_AMOUNT,
            'The amount "{{ amount }}" must be a positive integer in the smallest currency unit.',
            ['amount' => $amount]
        );
    }

    public static function amountMismatch(string $paymentNo, int $expected, int $actual): self
    {
        return new self(
            Response::HTTP_CONFLICT,
            self::AMOUNT_MISMATCH,
            'The notified amount of "{{ paymentNo }}" is {{ actual }} but {{ expected }} was expected.',
            ['paymentNo' => $paymentNo, 'expected' => $expected, 'actual' => $actual]
        );
    }

    public static function refundNotAllowed(string $paymentNo): self
    {
        return new self(
            Response::HTTP_CONFLICT,
            self::REFUND_NOT_ALLOWED,
            'The payment order "{{ paymentNo }}" is not paid and therefore cannot be refunded.',
            ['paymentNo' => $paymentNo]
        );
    }

    public static function refundAmountExceedsOrder(string $paymentNo, int $amount, int $available): self
    {
        return new self(
            Response::HTTP_CONFLICT,
            self::REFUND_AMOUNT_EXCEEDS_ORDER,
            'The refund amount {{ amount }} of "{{ paymentNo }}" exceeds the refundable amount {{ available }}.',
            ['paymentNo' => $paymentNo, 'amount' => $amount, 'available' => $available]
        );
    }

    public static function notificationInvalid(string $channel, string $reason): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::NOTIFICATION_INVALID,
            'The notification of channel "{{ channel }}" is invalid: {{ reason }}',
            ['channel' => $channel, 'reason' => $reason]
        );
    }

    public static function notificationOrderUnknown(string $channel, string $reference): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::NOTIFICATION_ORDER_UNKNOWN,
            'The notification of channel "{{ channel }}" references the unknown payment order "{{ reference }}".',
            ['channel' => $channel, 'reference' => $reference]
        );
    }

    public static function notificationNotFound(string $id): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::NOTIFICATION_NOT_FOUND,
            'The payment notification "{{ id }}" does not exist.',
            ['id' => $id]
        );
    }

    public static function webhookDeliveryNotFound(string $id): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::WEBHOOK_DELIVERY_NOT_FOUND,
            'The payment webhook delivery "{{ id }}" does not exist.',
            ['id' => $id]
        );
    }

    public static function gatewayError(string $channel, string $reason): self
    {
        return new self(
            Response::HTTP_BAD_GATEWAY,
            self::GATEWAY_ERROR,
            'The payment channel "{{ channel }}" returned an error: {{ reason }}',
            ['channel' => $channel, 'reason' => $reason]
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

    public static function gatewayResultMissing(string $channel): self
    {
        return new self(
            Response::HTTP_BAD_GATEWAY,
            self::GATEWAY_RESULT_MISSING,
            'The payment channel "{{ channel }}" did not return a result.',
            ['channel' => $channel]
        );
    }

    public static function refundNotFound(string $refundNo): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::REFUND_NOT_FOUND,
            'The refund "{{ refundNo }}" does not exist.',
            ['refundNo' => $refundNo]
        );
    }

    /**
     * Payum handed the action an api it does not work with - the gateway declaration and the action
     * drifted apart, which is a programming error rather than a problem of the caller.
     */
    public static function unsupportedApi(string $action, string $api): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::UNSUPPORTED_API,
            'The action "{{ action }}" does not support the api "{{ api }}".',
            ['action' => $action, 'api' => $api]
        );
    }

    /**
     * The action was executed without an api, which payum always injects before - a wiring error.
     */
    public static function actionWithoutApi(string $action): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::ACTION_WITHOUT_API,
            'The action "{{ action }}" was executed without an api.',
            ['action' => $action]
        );
    }
}
