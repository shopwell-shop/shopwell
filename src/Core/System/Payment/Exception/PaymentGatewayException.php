<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Exception;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\PaymentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
class PaymentGatewayException extends PaymentException
{
    public static function gatewayResultMissing(string $channel): self
    {
        return new self(
            Response::HTTP_BAD_GATEWAY,
            self::GATEWAY_RESULT_MISSING,
            'The payment channel "{{ channel }}" did not return a result.',
            ['channel' => $channel]
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
}
