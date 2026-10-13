<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Sms;

use Shopwell\Core\Framework\HttpException;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class SmsException extends HttpException
{
    public const UNKNOWN_GATEWAY = 'SMS__UNKNOWN_GATEWAY';
    public const INVALID_PHONE_NUMBER = 'SMS__INVALID_PHONE_NUMBER';
    public const TEMPLATE_NOT_FOUND = 'SMS__TEMPLATE_NOT_FOUND';
    public const TEMPLATE_DISABLED = 'SMS__TEMPLATE_DISABLED';
    public const TEMPLATE_MODE_REQUIRED = 'SMS__TEMPLATE_MODE_REQUIRED';
    public const TEMPLATE_NOT_CONFIGURED = 'SMS__TEMPLATE_NOT_CONFIGURED';
    public const TEMPLATE_NOT_APPROVED = 'SMS__TEMPLATE_NOT_APPROVED';
    public const TEMPLATE_CONTENT_MISSING = 'SMS__TEMPLATE_CONTENT_MISSING';
    public const INVALID_TEMPLATE_PARAMS = 'SMS__INVALID_TEMPLATE_PARAMS';
    public const MESSAGE_TOO_LONG = 'SMS__MESSAGE_TOO_LONG';
    public const PROVIDER_UNAVAILABLE = 'SMS__PROVIDER_UNAVAILABLE';

    /**
     * @param list<string> $available
     */
    public static function unknownGateway(string $technicalName, array $available): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::UNKNOWN_GATEWAY,
            'The sms gateway "{{ technicalName }}" is unknown, registered are: {{ available }}.',
            ['technicalName' => $technicalName, 'available' => implode(', ', $available)]
        );
    }

    public static function invalidPhoneNumber(string $phone): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_PHONE_NUMBER,
            'The phone number "{{ phone }}" is not a valid e.164 number.',
            ['phone' => $phone]
        );
    }

    public static function templateNotFound(string $technicalName): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::TEMPLATE_NOT_FOUND,
            'The sms template "{{ technicalName }}" does not exist.',
            ['technicalName' => $technicalName]
        );
    }

    public static function templateDisabled(string $technicalName): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::TEMPLATE_DISABLED,
            'The sms template "{{ technicalName }}" is disabled.',
            ['technicalName' => $technicalName]
        );
    }

    public static function templateModeRequired(string $technicalName): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::TEMPLATE_MODE_REQUIRED,
            'The sms gateway "{{ technicalName }}" sends reported templates only, the message carries no template options.',
            ['technicalName' => $technicalName]
        );
    }

    public static function templateNotConfigured(string $templateRef, string $technicalName): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::TEMPLATE_NOT_CONFIGURED,
            'The sms template "{{ templateRef }}" has no mapping for the gateway "{{ technicalName }}".',
            ['templateRef' => $templateRef, 'technicalName' => $technicalName]
        );
    }

    public static function templateNotApproved(string $templateRef, string $technicalName, string $auditStatus): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::TEMPLATE_NOT_APPROVED,
            'The sms template "{{ templateRef }}" is "{{ auditStatus }}" at "{{ technicalName }}", only approved templates may be sent.',
            ['templateRef' => $templateRef, 'technicalName' => $technicalName, 'auditStatus' => $auditStatus]
        );
    }

    public static function templateContentMissing(string $templateRef): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::TEMPLATE_CONTENT_MISSING,
            'The sms template "{{ templateRef }}" has no content, a gateway sending free text cannot render it.',
            ['templateRef' => $templateRef]
        );
    }

    /**
     * @param list<string> $violations
     */
    public static function invalidTemplateParams(string $templateRef, array $violations): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_TEMPLATE_PARAMS,
            'The parameters of the sms template "{{ templateRef }}" are invalid: {{ violations }}',
            ['templateRef' => $templateRef, 'violations' => implode(', ', $violations)]
        );
    }

    public static function messageTooLong(string $templateRef, int $length, int $maxLength): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::MESSAGE_TOO_LONG,
            'The rendered sms of "{{ templateRef }}" has {{ length }} characters, the maximum is {{ maxLength }}.',
            ['templateRef' => $templateRef, 'length' => (string) $length, 'maxLength' => (string) $maxLength]
        );
    }

    /**
     * The provider answered, but asked to be tried again later - the failure is worth a retry, which
     * is the difference to SmsUnrecoverableException.
     */
    public static function providerUnavailable(string $technicalName, string $providerCode, string $providerMessage): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::PROVIDER_UNAVAILABLE,
            'The sms gateway "{{ technicalName }}" could not send right now ({{ providerCode }}): {{ providerMessage }}',
            [
                'technicalName' => $technicalName,
                'providerCode' => $providerCode,
                'providerMessage' => $providerMessage,
            ]
        );
    }
}
