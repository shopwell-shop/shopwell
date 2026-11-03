<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Exception;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\PaymentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
class PaymentProviderException extends PaymentException
{
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

    /**
     * The account sources of an interface run in a circle: app A inherits from app B, app B inherits
     * from app A again. Neither of them owns an account, so the chain has no end and no credentials -
     * it is a configuration mistake and is refused instead of being walked until a hop limit hits.
     */
    public static function providerInheritanceCycle(string $technicalName, string $appTechnicalName): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::PROVIDER_INHERITANCE_CYCLE,
            'The account source of the payment provider "{{ technicalName }}" runs in a circle: the app "{{ appTechnicalName }}" inherits it from an app that inherits it from "{{ appTechnicalName }}" again. Exactly one app of the chain has to collect on an account of its own.',
            ['technicalName' => $technicalName, 'appTechnicalName' => $appTechnicalName]
        );
    }

    /**
     * The account of an interface is inherited from an app that does not exist anymore. The column is
     * a foreign key with `ON DELETE SET NULL`, so this only happens for a row that was written by
     * hand or by an import - a clear answer beats walking into "no credentials" without saying why.
     */
    public static function providerSourceAppMissing(string $technicalName, string $appTechnicalName, string $sourceAppId): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::PROVIDER_SOURCE_APP_MISSING,
            'The app "{{ appTechnicalName }}" inherits the account of the payment provider "{{ technicalName }}" from the app "{{ sourceAppId }}", which does not exist.',
            ['technicalName' => $technicalName, 'appTechnicalName' => $appTechnicalName, 'sourceAppId' => $sourceAppId]
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
}
