<?php declare(strict_types=1);

namespace Shopwell\Core\System\Sms;

use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * A message that will not succeed on another try.
 *
 * The provider refusing a message - a template that was never reported, a signature that is not
 * approved, a number that is on a block list, a parameter the template does not declare - is final,
 * and so is a gateway whose credentials are incomplete or that has no signature to send with.
 *
 * SendSmsHandler turns this exception into an UnrecoverableMessageHandlingException, so the queue
 * stops after the first attempt instead of sending the same message again for every retry it was
 * configured with - which would cost the shop the fee for every attempt.
 *
 * A failure that can pass later - the endpoint not reachable, a system error at the provider, a rate
 * limit - is thrown as the plain SmsException and is retried.
 *
 * @internal
 */
#[Package('framework')]
final class SmsUnrecoverableException extends SmsException
{
    public const PROVIDER_REJECTED = 'SMS__PROVIDER_REJECTED';
    public const CREDENTIALS_INCOMPLETE = 'SMS__CREDENTIALS_INCOMPLETE';
    public const SIGN_NAME_MISSING = 'SMS__SIGN_NAME_MISSING';

    public static function providerRejected(string $technicalName, string $providerCode, string $providerMessage): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::PROVIDER_REJECTED,
            'The sms gateway "{{ technicalName }}" rejected the message ({{ providerCode }}): {{ providerMessage }}',
            [
                'technicalName' => $technicalName,
                'providerCode' => $providerCode,
                'providerMessage' => $providerMessage,
            ]
        );
    }

    /**
     * @param list<string> $missing
     */
    public static function credentialsIncomplete(string $technicalName, array $missing): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::CREDENTIALS_INCOMPLETE,
            'The sms gateway "{{ technicalName }}" is missing the credentials {{ missing }}, it cannot send.',
            ['technicalName' => $technicalName, 'missing' => implode(', ', $missing)]
        );
    }

    public static function signNameMissing(string $technicalName): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::SIGN_NAME_MISSING,
            'The sms gateway "{{ technicalName }}" needs a signature, neither the template mapping nor the account names one.',
            ['technicalName' => $technicalName]
        );
    }
}
