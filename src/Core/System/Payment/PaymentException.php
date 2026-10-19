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
    public const DUPLICATE_PROVIDER = 'PAYMENT__DUPLICATE_PROVIDER';
    public const AMBIGUOUS_PROVIDER_PRIORITY = 'PAYMENT__AMBIGUOUS_PROVIDER_PRIORITY';
    public const INVALID_PROVIDER_NAME = 'PAYMENT__INVALID_PROVIDER_NAME';
    public const INVALID_PROVIDER_DECLARATION = 'PAYMENT__INVALID_PROVIDER_DECLARATION';
    public const STATE_MACHINE_INVALID = 'PAYMENT__STATE_MACHINE_INVALID';

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

    public const PAYMENT_METHOD_CHANNEL_MISSING = 'PAYMENT__PAYMENT_METHOD_CHANNEL_MISSING';
    public const CHANNEL_NOT_AVAILABLE_IN_STOREFRONT = 'PAYMENT__CHANNEL_NOT_AVAILABLE_IN_STOREFRONT';

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
    public const TRANSFER_ALREADY_FINALIZED = 'PAYMENT__TRANSFER_ALREADY_FINALIZED';
    public const TRANSFER_FOREIGN_APP = 'PAYMENT__TRANSFER_FOREIGN_APP';
    public const TRANSFER_PROVIDER_REF_MISSING = 'PAYMENT__TRANSFER_PROVIDER_REF_MISSING';
    public const TRANSFER_NOTIFICATION_APP_UNKNOWN = 'PAYMENT__TRANSFER_NOTIFICATION_APP_UNKNOWN';
    public const TRANSFER_NOTIFICATION_PROVIDER_UNKNOWN = 'PAYMENT__TRANSFER_NOTIFICATION_PROVIDER_UNKNOWN';
    public const TRANSFER_NOTIFICATION_TRANSFER_UNKNOWN = 'PAYMENT__TRANSFER_NOTIFICATION_TRANSFER_UNKNOWN';

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

    /**
     * Two implementations of the very same technical name, where the second one is not a decorator.
     *
     * A technical name is a foreign key value - `payment_order.provider_technical_name`,
     * `payment_provider_config.provider_technical_name`, `payment_app_provider.provider_technical_name` -
     * so a second implementation that silently takes over would make every historic row
     * uninterpretable. Extending an interface is done by decorating it, replacing it by giving the
     * replacement a technical name of its own. The container refuses to compile, which is the only
     * moment the conflict can still be answered with "fix the declaration".
     */
    public static function duplicateProvider(string $technicalName, string $firstDeclaration, string $secondDeclaration): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::DUPLICATE_PROVIDER,
            'The payment provider "{{ technicalName }}" is declared by "{{ firstDeclaration }}" and by "{{ secondDeclaration }}", but the second one is not a decorator. Declare a technical name of its own or decorate the existing declaration through getDecorated().',
            ['technicalName' => $technicalName, 'firstDeclaration' => $firstDeclaration, 'secondDeclaration' => $secondDeclaration]
        );
    }

    /**
     * Two declarations of one technical name that carry the very same priority.
     *
     * The priority is what orders the decoration chain, so one priority for two declarations means the
     * order is decided by the container - by the order the services happen to be registered in. That
     * order changes when an unrelated plugin installs itself, which would silently swap the outermost
     * decorator, i.e. the instance that represents the interface. Since a chain only has one valid
     * order, the ambiguity is refused at compile time instead of being resolved by chance.
     */
    public static function ambiguousProviderPriority(
        string $technicalName,
        int $priority,
        string $firstDeclaration,
        string $secondDeclaration,
    ): self {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::AMBIGUOUS_PROVIDER_PRIORITY,
            'The payment provider "{{ technicalName }}" is declared by "{{ firstDeclaration }}" and by "{{ secondDeclaration }}", both with the priority {{ priority }}. The priority orders the decoration chain, so give them different priorities.',
            [
                'technicalName' => $technicalName,
                'priority' => $priority,
                'firstDeclaration' => $firstDeclaration,
                'secondDeclaration' => $secondDeclaration,
            ]
        );
    }

    /**
     * A technical name that the notify url could not carry.
     *
     * The route of the notify endpoint is `/payment-api/v1/notify/{provider}/{paymentNo}` with the
     * requirement `[A-Za-z0-9_-]+` (`Api/NotifyController`), so a name outside of that character set
     * would produce an interface whose callbacks can never arrive. It is refused at container compile
     * time instead of at the first payment.
     */
    public static function invalidProviderName(string $technicalName): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::INVALID_PROVIDER_NAME,
            'The payment provider technical name "{{ technicalName }}" is invalid, it must match [A-Za-z0-9_-]+ so that the notify url can carry it.',
            ['technicalName' => $technicalName]
        );
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

    /**
     * The tenant of the shop is seeded by the migration and cannot be created at runtime, so its
     * absence means the installation or the upgrade is broken - not the request that hit it.
     */
    public static function platformTenantMissing(string $technicalName): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::PLATFORM_TENANT_MISSING,
            'The platform tenant "{{ technicalName }}" is missing. The payment migration has not seeded it.',
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

    /**
     * The insert of a payment order hit a unique key, but neither the out trade number nor the
     * idempotency key reads the conflicting order back - the order exists, it is just not visible
     * to this request yet, so retrying is the right answer and "not found" would be a lie.
     */
    public static function orderConflict(string $outTradeNo): self
    {
        return new self(
            Response::HTTP_CONFLICT,
            self::ORDER_CONFLICT,
            'The payment order for the out trade number "{{ outTradeNo }}" conflicts with an existing order that cannot be read back.',
            ['outTradeNo' => $outTradeNo]
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

    /**
     * The order cannot be closed because the payment already arrived: the close is refused instead of
     * hiding a payment the platform has to book.
     */
    public static function orderAlreadyPaid(string $paymentNo): self
    {
        return new self(
            Response::HTTP_CONFLICT,
            self::ORDER_ALREADY_PAID,
            'The payment order "{{ paymentNo }}" is already paid and cannot be closed.',
            ['paymentNo' => $paymentNo]
        );
    }

    public static function orderNotExpirable(string $paymentNo): self
    {
        return new self(
            Response::HTTP_CONFLICT,
            self::ORDER_NOT_EXPIRABLE,
            'The payment order "{{ paymentNo }}" can no longer expire.',
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

    public static function currencyMismatch(string $paymentNo, string $expected, string $actual): self
    {
        return new self(
            Response::HTTP_CONFLICT,
            self::CURRENCY_MISMATCH,
            'The notified currency of "{{ paymentNo }}" is "{{ actual }}" but "{{ expected }}" was expected.',
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

    /**
     * A subscription plan whose monthly deduction day falls after the 28th cannot be signed up at
     * alipay at all: `period_rule_params.execute_time` is capped there by the provider. Refusing
     * at plan level beats silently moving the deduction day - the subscriber would be charged on
     * a day they never agreed to.
     */
    public static function paymentCycleNotExpressible(string $provider, string $unit, int $count, int $anchorDayOfMonth): self
    {
        return new self(
            Response::HTTP_UNPROCESSABLE_ENTITY,
            self::CYCLE_NOT_EXPRESSIBLE,
            'A subscription charging every {{ count }} {{ unit }} on day {{ anchorDayOfMonth }} of the month cannot be expressed at the provider "{{ provider }}": monthly deduction days are limited to the 28th there.',
            ['provider' => $provider, 'unit' => $unit, 'count' => $count, 'anchorDayOfMonth' => $anchorDayOfMonth]
        );
    }

    public static function paymentCycleNotFound(string $id): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::CYCLE_NOT_FOUND,
            'The subscription cycle "{{ id }}" does not exist.',
            ['id' => $id]
        );
    }

    /**
     * The insert of a cycle hit the unique key over (subscription, cycle number), but the
     * conflicting row cannot be read back in this request - the cycle exists, it is just not
     * visible yet, so retrying is the right answer and "not found" would be a lie.
     */
    public static function paymentCycleConflict(string $subscriptionId, int $cycleNo): self
    {
        return new self(
            Response::HTTP_CONFLICT,
            self::CYCLE_CONFLICT,
            'The cycle {{ cycleNo }} of the subscription "{{ subscriptionId }}" conflicts with an existing cycle that cannot be read back.',
            ['subscriptionId' => $subscriptionId, 'cycleNo' => $cycleNo]
        );
    }

    /**
     * Only a cycle the channel answered definitively for may be re-armed: an in-flight trade has
     * to stay `unknown` until a query resolves it, and a settled cycle has nothing to re-arm.
     */
    public static function paymentCycleNotRearmable(string $id, string $state): self
    {
        return new self(
            Response::HTTP_CONFLICT,
            self::CYCLE_NOT_REARMABLE,
            'The subscription cycle "{{ id }}" is "{{ state }}" and cannot be re-armed for another charge attempt.',
            ['id' => $id, 'state' => $state]
        );
    }

    public static function subscriptionNotFound(string $id): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::SUBSCRIPTION_NOT_FOUND,
            'The payment subscription "{{ id }}" does not exist.',
            ['id' => $id]
        );
    }

    public static function invalidPeriodUnit(string $unit): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_PERIOD_UNIT,
            'The period unit "{{ unit }}" is not one of day, week, month or year.',
            ['unit' => $unit]
        );
    }

    public static function invalidPeriodCount(int $count): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_PERIOD_COUNT,
            'A period must advance by at least 1, got {{ count }}.',
            ['count' => $count]
        );
    }

    public static function transferNotFound(string $id): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::TRANSFER_NOT_FOUND,
            'The payment transfer "{{ id }}" does not exist.',
            ['id' => $id]
        );
    }

    /**
     * The insert hit a unique key, but the conflicting row cannot be read back in this request - it
     * exists, it is just not visible yet, so retrying is the right answer and "not found" would be a
     * lie. Both unique keys of the domain report through this factory: the out number of a transfer
     * and the identity hash of a recipient.
     */
    public static function transferConflict(string $reference): self
    {
        return new self(
            Response::HTTP_CONFLICT,
            self::TRANSFER_CONFLICT,
            'The payment transfer "{{ reference }}" conflicts with an existing row that cannot be read back.',
            ['reference' => $reference]
        );
    }

    public static function transferRecipientNotFound(string $id): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::TRANSFER_RECIPIENT_NOT_FOUND,
            'The payment transfer recipient "{{ id }}" does not exist.',
            ['id' => $id]
        );
    }

    /**
     * The interface cannot pay out to this kind of identity at all - wechat only knows an openid of
     * the very app, stripe only a connected account, alipay no paypal email. Rejecting the pair at
     * intake beats building a request the interface is guaranteed to refuse.
     */
    public static function transferIdentityNotSupported(string $provider, string $identityType): self
    {
        return new self(
            Response::HTTP_UNPROCESSABLE_ENTITY,
            self::TRANSFER_IDENTITY_NOT_SUPPORTED,
            'The payment interface "{{ provider }}" cannot pay out to the identity type "{{ identityType }}".',
            ['provider' => $provider, 'identityType' => $identityType]
        );
    }

    /**
     * A field the interface demands for this combination is missing - the name of the holder for an
     * alipay logon id, the bank details for a card, the name of the holder from 2000 yuan on at wechat.
     */
    public static function transferRecipientIncomplete(string $field): self
    {
        return new self(
            Response::HTTP_UNPROCESSABLE_ENTITY,
            self::TRANSFER_RECIPIENT_INCOMPLETE,
            'The transfer recipient is missing the field "{{ field }}" that the interface requires.',
            ['field' => $field]
        );
    }

    /**
     * The plain identity has to fit into the encrypted column, and the ciphertext is larger than the
     * plain text: `PaymentCredentialCipher::encryptValue()` returns
     * `base64_encode(nonce(24) + sodium secretbox(plain + 16))`, so a plain text of `n` bytes becomes
     * `4 * ceil((n + 40) / 3)` characters. With `identity_value` being `VARCHAR(255)` the limit is
     * therefore `4 * ceil((n + 40) / 3) <= 255`, i.e. `n <= 149`.
     *
     * The limit is enforced before encrypting on purpose: the write would otherwise fail with MySQL
     * error 1406 ("Data too long") and surface as a 500 instead of a readable 422. It is not a reason
     * to widen the column - 149 bytes already cover every real identity (paypal's `receiver` tops out
     * at 127 characters, and every identity type is ASCII), and a wider column only wastes storage on
     * ciphertext.
     */
    public static function transferIdentityValueTooLong(int $length): self
    {
        return new self(
            Response::HTTP_UNPROCESSABLE_ENTITY,
            self::TRANSFER_IDENTITY_VALUE_TOO_LONG,
            'The transfer recipient identity is {{ length }} bytes long and must not exceed 149 bytes, so that its encrypted form fits into the 255 character column.',
            ['length' => $length]
        );
    }

    public static function transferAmountInvalid(): self
    {
        return new self(
            Response::HTTP_UNPROCESSABLE_ENTITY,
            self::TRANSFER_AMOUNT_INVALID,
            'The transfer amount must be a positive integer in the smallest currency unit.',
            []
        );
    }

    /**
     * The out number is the idempotency key of a payout and the interfaces cap it: wechat allows 32
     * characters of `[0-9a-zA-Z]` on `out_bill_no`, alipay 64 on `out_biz_no`, and paypal 30 on the
     * batch and item ids it derives from the number. The strictest limit wins, and a payout that
     * cannot be sent under the number it was booked with must never be booked at all.
     */
    public static function transferOutNoInvalid(string $outNo): self
    {
        return new self(
            Response::HTTP_UNPROCESSABLE_ENTITY,
            self::TRANSFER_OUT_NO_INVALID,
            'The out number "{{ outNo }}" must consist of 1 to 30 characters of [0-9a-zA-Z].',
            ['outNo' => $outNo]
        );
    }

    /**
     * The transfer is already past the point of submission. Sending it again would not be a retry but
     * a second payout - a retry always queries the channel first ("query before sending").
     */
    public static function transferAlreadyFinalized(string $outNo, string $state): self
    {
        return new self(
            Response::HTTP_CONFLICT,
            self::TRANSFER_ALREADY_FINALIZED,
            'The payment transfer "{{ outNo }}" is "{{ state }}" and can not be submitted again.',
            ['outNo' => $outNo, 'state' => $state]
        );
    }

    /**
     * The credentials of a payout are resolved from the app the caller acts for, so a transfer of
     * another app would be paid out of that merchant account. The out number is the business case and
     * is what the caller can act on - the transfer id is an internal detail.
     */
    public static function transferForeignApp(string $appId, string $outNo): self
    {
        return new self(
            Response::HTTP_FORBIDDEN,
            self::TRANSFER_FOREIGN_APP,
            'The payment transfer "{{ outNo }}" does not belong to the app "{{ appId }}".',
            ['appId' => $appId, 'outNo' => $outNo]
        );
    }

    /**
     * A transfer that reached the interface has to name the object the interface created - every later
     * query and every notification is matched by it. A row in an open state without one is a data
     * defect of the submission, not a missing field of the recipient, and has to be reported as such:
     * no query can be made and no answer can be booked.
     */
    public static function transferProviderRefMissing(string $outNo): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::TRANSFER_PROVIDER_REF_MISSING,
            'The payment transfer "{{ outNo }}" carries no provider reference and can not be queried at the channel.',
            ['outNo' => $outNo]
        );
    }

    /**
     * The interface does not pay out to a recipient that has not finished its onboarding: stripe
     * refuses every payout of a connected account whose `payouts_enabled` is not true. Refusing the
     * call locally - before a single request is built - beats using the error code of the interface
     * as a validator, which would burn the idempotency key of the transfer on a guaranteed refusal.
     */
    public static function transferRecipientNotReady(string $recipientId): self
    {
        return new self(
            Response::HTTP_CONFLICT,
            self::TRANSFER_RECIPIENT_NOT_READY,
            'The transfer recipient "{{ recipientId }}" is not ready to be paid out on the side of the interface.',
            ['recipientId' => $recipientId]
        );
    }

    /**
     * A payout notification names an app in its url, and an app that does not exist has no merchant
     * account whose signing key could verify the notification. The endpoint answers it the same way it
     * answers an unknown interface: not found, without saying which of the two was wrong.
     */
    public static function transferNotificationAppUnknown(string $appId): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::TRANSFER_NOTIFICATION_APP_UNKNOWN,
            'The app "{{ appId }}" of the payout notification does not exist.',
            ['appId' => $appId]
        );
    }

    /**
     * A payout notification can only be verified by the interface that sent it. An interface the
     * platform does not ship has no verifier, and guessing one would mean trusting an unverified body.
     */
    public static function transferNotificationProviderUnknown(string $provider): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::TRANSFER_NOTIFICATION_PROVIDER_UNKNOWN,
            'The payment interface "{{ provider }}" can not verify a payout notification.',
            ['provider' => $provider]
        );
    }

    /**
     * The signed body of a payout notification named a payout the app does not have. It is a defect of
     * the caller or a payout of another merchant account, and neither becomes true by repeating the
     * notification.
     */
    public static function transferNotificationTransferUnknown(string $outNo): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::TRANSFER_NOTIFICATION_TRANSFER_UNKNOWN,
            'The payout "{{ outNo }}" named by the notification does not exist for this app.',
            ['outNo' => $outNo]
        );
    }
}
