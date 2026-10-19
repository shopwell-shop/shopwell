<?php declare(strict_types=1);

namespace Shopwell\Core\System\Sms;

use Shopwell\Core\Framework\HttpException;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Sms\Exception\SmsUnrecoverableException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
class SmsException extends HttpException
{
    public const UNKNOWN_GATEWAY = 'SMS__UNKNOWN_GATEWAY';
    public const INVALID_PHONE_NUMBER = 'SMS__INVALID_PHONE_NUMBER';
    public const PHONE_NUMBER_NOT_INTERNATIONAL = 'SMS__PHONE_NUMBER_NOT_INTERNATIONAL';

    /**
     * Not thrown, only written to the error_code of a delivery record when no gateway could be
     * chosen for a message.
     */
    public const NO_USABLE_GATEWAY = 'SMS__NO_USABLE_GATEWAY';

    public const TEMPLATE_NOT_FOUND = 'SMS__TEMPLATE_NOT_FOUND';
    public const TEMPLATE_DISABLED = 'SMS__TEMPLATE_DISABLED';
    public const TEMPLATE_MODE_REQUIRED = 'SMS__TEMPLATE_MODE_REQUIRED';
    public const TEMPLATE_NOT_CONFIGURED = 'SMS__TEMPLATE_NOT_CONFIGURED';
    public const TEMPLATE_NOT_APPROVED = 'SMS__TEMPLATE_NOT_APPROVED';
    public const TEMPLATE_CONTENT_MISSING = 'SMS__TEMPLATE_CONTENT_MISSING';
    public const INVALID_TEMPLATE_PARAMS = 'SMS__INVALID_TEMPLATE_PARAMS';
    public const MESSAGE_TOO_LONG = 'SMS__MESSAGE_TOO_LONG';
    public const PROVIDER_UNAVAILABLE = 'SMS__PROVIDER_UNAVAILABLE';
    public const PROVIDER_REJECTED = 'SMS__PROVIDER_REJECTED';
    public const CREDENTIALS_INCOMPLETE = 'SMS__CREDENTIALS_INCOMPLETE';
    public const SIGN_NAME_MISSING = 'SMS__SIGN_NAME_MISSING';
    public const SIGN_NAME_NOT_APPROVED = 'SMS__SIGN_NAME_NOT_APPROVED';
    public const TEMPLATE_SUBMISSION_UNSUPPORTED = 'SMS__TEMPLATE_SUBMISSION_UNSUPPORTED';
    public const TEMPLATE_STATUS_READ_UNSUPPORTED = 'SMS__TEMPLATE_STATUS_READ_UNSUPPORTED';
    public const TEMPLATE_SUBMISSION_INVALID = 'SMS__TEMPLATE_SUBMISSION_INVALID';
    public const TEMPLATE_AUDIT_ACCOUNT_REQUIRED = 'SMS__TEMPLATE_AUDIT_ACCOUNT_REQUIRED';
    public const TEMPLATE_AUDIT_RESPONSE_INVALID = 'SMS__TEMPLATE_AUDIT_RESPONSE_INVALID';
    public const TEMPLATE_AUDIT_CONFIRMATION_INVALID = 'SMS__TEMPLATE_AUDIT_CONFIRMATION_INVALID';

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

    /**
     * The number carries no country code and the caller passed none either - the module never
     * guesses a country, because a guess sends the message to a stranger.
     */
    public static function phoneNumberNotInternational(string $phone): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::PHONE_NUMBER_NOT_INTERNATIONAL,
            'The phone number "{{ phone }}" carries no country code, send it in the international format +<country code> <number>.',
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

    public static function providerRejected(string $technicalName, string $providerCode, string $providerMessage): SmsUnrecoverableException
    {
        return new SmsUnrecoverableException(
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
    public static function credentialsIncomplete(string $technicalName, array $missing): SmsUnrecoverableException
    {
        return new SmsUnrecoverableException(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::CREDENTIALS_INCOMPLETE,
            'The sms gateway "{{ technicalName }}" is missing the credentials {{ missing }}, it cannot send.',
            ['technicalName' => $technicalName, 'missing' => implode(', ', $missing)]
        );
    }

    public static function signNameMissing(string $technicalName): SmsUnrecoverableException
    {
        return new SmsUnrecoverableException(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::SIGN_NAME_MISSING,
            'The sms gateway "{{ technicalName }}" needs a signature, neither the template mapping nor the account names one.',
            ['technicalName' => $technicalName]
        );
    }

    /**
     * The signature exists but the provider did not approve it - a case of its own, so an operator
     * does not go looking for a template problem.
     */
    public static function signNameNotApproved(
        string $technicalName,
        string $signName,
        string $providerCode,
        string $providerMessage,
    ): SmsUnrecoverableException {
        return new SmsUnrecoverableException(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::SIGN_NAME_NOT_APPROVED,
            'The signature "{{ signName }}" of the sms gateway "{{ technicalName }}" is not approved at the provider ({{ providerCode }}): {{ providerMessage }}',
            [
                'technicalName' => $technicalName,
                'signName' => $signName,
                'providerCode' => $providerCode,
                'providerMessage' => $providerMessage,
            ]
        );
    }

    public static function templateSubmissionUnsupported(string $technicalName): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::TEMPLATE_SUBMISSION_UNSUPPORTED,
            'The sms provider "{{ technicalName }}" cannot submit templates, report them at the console of the provider and set the state by hand.',
            ['technicalName' => $technicalName]
        );
    }

    public static function templateStatusReadUnsupported(string $technicalName): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::TEMPLATE_STATUS_READ_UNSUPPORTED,
            'The sms provider "{{ technicalName }}" cannot report the state of a template, check it at the console of the provider.',
            ['technicalName' => $technicalName]
        );
    }

    /**
     * @param list<string> $violations
     */
    public static function templateSubmissionInvalid(string $templateRef, string $technicalName, array $violations): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::TEMPLATE_SUBMISSION_INVALID,
            'The submission of the sms template "{{ templateRef }}" to "{{ technicalName }}" is incomplete: {{ violations }}',
            ['templateRef' => $templateRef, 'technicalName' => $technicalName, 'violations' => implode(', ', $violations)]
        );
    }

    public static function templateAuditAccountRequired(string $templateRef, string $technicalName): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::TEMPLATE_AUDIT_ACCOUNT_REQUIRED,
            'The sms template "{{ templateRef }}" has no gateway account for "{{ technicalName }}", pick the account that submits the template.',
            ['templateRef' => $templateRef, 'technicalName' => $technicalName]
        );
    }

    public static function templateAuditResponseInvalid(string $technicalName, string $providerMessage): self
    {
        return new self(
            Response::HTTP_BAD_GATEWAY,
            self::TEMPLATE_AUDIT_RESPONSE_INVALID,
            'The sms gateway "{{ technicalName }}" answered without the data the audit needs: {{ providerMessage }}',
            ['technicalName' => $technicalName, 'providerMessage' => $providerMessage]
        );
    }

    /**
     * @param list<string> $violations
     */
    public static function templateAuditConfirmationInvalid(string $templateRef, string $technicalName, array $violations): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::TEMPLATE_AUDIT_CONFIRMATION_INVALID,
            'The manual confirmation of the sms template "{{ templateRef }}" at "{{ technicalName }}" is invalid: {{ violations }}',
            ['templateRef' => $templateRef, 'technicalName' => $technicalName, 'violations' => implode(', ', $violations)]
        );
    }
}
