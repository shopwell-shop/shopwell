<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Sso;

use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\Api\Context\ContextSource;
use Shopwell\Core\Framework\Api\Context\Exception\InvalidContextSourceException;
use Shopwell\Core\Framework\HttpException;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Sso\Exceptions\SsoUserNotFoundException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class SsoException extends HttpException
{
    public const SSO_USER_INVITATION_MAIL_TEMPLATE_NOT_FOUND = 'SSO_USER_INVITATION_MAIL_TEMPLATE_NOT_FOUND';
    public const SSO_LOGIN_CONFIG_NOT_FOUND = 'SSO_LOGIN_CONFIG__NOT_FOUND';
    public const SSO_LOGIN_CONFIG_INCOMPLETE_OR_MISCONFIGURED = 'SSO_LOGIN_CONFIG__INCOMPLETE_OR_MISCONFIGURED';

    public const SSO_LOGIN_USER_INVALID = 'SSO_LOGIN__USER_INVALID';
    public const SSO_LOGIN_INVALID_LOGIN_STATE = 'SSO_LOGIN__INVALID_LOGIN_STATE';
    public const SSO_LOGIN_INVALID_TOKEN_RESPONSE = 'SSO_LOGIN__INVALID_TOKEN_RESPONSE';
    public const SSO_LOGIN_INVALID_ID_TOKEN_DATA_SET = 'SSO_LOGIN__INVALID_ID_TOKEN_DATA_SET';
    public const SSO_LOGIN_INVALID_REFRESH_OR_ACCESS_TOKEN = 'SSO_LOGIN__INVALID_REFRESH_OR_ACCESS_TOKEN';
    public const SSO_LOGIN_INVALID_REQUEST_NO_CODE_PROVIDED = 'SSO_LOGIN__INVALID_REQUEST_NO_CODE_PROVIDED';
    public const SSO_LOGIN_PUBLIC_KEY_NOT_FOUND = 'SSO_LOGIN__PUBLIC_KEY_NOT_FOUND';
    public const SSO_LOGIN_INVALID_ID_TOKEN = 'SSO_LOGIN__INVALID_ID_TOKEN';
    public const SSO_LOGIN_INVALID_PUBLIC_KEY = 'SSO_LOGIN__INVALID_PUBLIC_KEY';
    public const SSO_LOGIN_NEGATIVE_TIME_TO_LIVE = 'SSO_LOGIN__NEGATIVE_TIME_TO_LIVE';
    public const SSO_LOGIN_TOKEN_NOT_FOUND = 'SSO_LOGIN__TOKEN_NOT_FOUND';

    public const OAUTH2_PROVIDER_NOT_AVAILABLE = 'SSO_OAUTH2__PROVIDER_NOT_AVAILABLE';
    public const OAUTH2_PROVIDER_NOT_CONFIGURED = 'SSO_OAUTH2__PROVIDER_NOT_CONFIGURED';
    public const OAUTH2_CHANNEL_DISABLED = 'SSO_OAUTH2__CHANNEL_DISABLED';
    public const OAUTH2_INVALID_STATE = 'SSO_OAUTH2__INVALID_STATE';
    public const OAUTH2_AUTHORIZATION_FAILED = 'SSO_OAUTH2__AUTHORIZATION_FAILED';
    public const OAUTH2_IDENTITY_MISSING = 'SSO_OAUTH2__IDENTITY_MISSING';
    public const OAUTH2_IDENTITY_NOT_BOUND = 'SSO_OAUTH2__IDENTITY_NOT_BOUND';
    public const OAUTH2_IDENTITY_ALREADY_BOUND = 'SSO_OAUTH2__IDENTITY_ALREADY_BOUND';
    public const OAUTH2_MISSING_VERIFIED_EMAIL = 'SSO_OAUTH2__MISSING_VERIFIED_EMAIL';
    public const OAUTH2_INVALID_RESOURCE_OWNER_RESPONSE = 'SSO_OAUTH2__INVALID_RESOURCE_OWNER_RESPONSE';
    public const OAUTH2_INVALID_LOGIN_CODE = 'SSO_OAUTH2__INVALID_LOGIN_CODE';
    public const OAUTH2_ADMIN_USER_NOT_FOUND = 'SSO_OAUTH2__ADMIN_USER_NOT_FOUND';
    public const OAUTH2_USER_NOT_LOGGED_IN = 'SSO_OAUTH2__USER_NOT_LOGGED_IN';

    /**
     * @param class-string<ContextSource> $actual
     */
    public static function invalidAdminSource(string $actual): InvalidContextSourceException
    {
        return new InvalidContextSourceException(AdminApiSource::class, $actual);
    }

    public static function userNotLoggedIn(): self
    {
        return new self(
            Response::HTTP_FORBIDDEN,
            self::OAUTH2_USER_NOT_LOGGED_IN,
            'For this interaction an authenticated user login is required.'
        );
    }

    public static function mailTemplateNotFound(): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::SSO_USER_INVITATION_MAIL_TEMPLATE_NOT_FOUND,
            'Mail template for sso user invitation not found'
        );
    }

    public static function loginConfigurationNotFound(): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::SSO_LOGIN_CONFIG_NOT_FOUND,
            'Login configuration not found'
        );
    }

    /**
     * @param array<string> $fieldErrors
     */
    public static function configurationMisconfigured(array $fieldErrors): self
    {
        $fields = implode(', ', $fieldErrors);

        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::SSO_LOGIN_CONFIG_INCOMPLETE_OR_MISCONFIGURED,
            'Login config is incomplete or misconfigured. Field errors: {{ fieldErrors }}',
            ['fieldErrors' => $fields]
        );
    }

    public static function userNotFound(string $email): SsoUserNotFoundException
    {
        return new SsoUserNotFoundException($email);
    }

    /**
     * @param array<int, string> $missingFields
     */
    public static function loginUserInvalid(array $missingFields): self
    {
        return new self(
            Response::HTTP_UNAUTHORIZED,
            self::SSO_LOGIN_USER_INVALID,
            'Login user invalid: {{ missingFields }}',
            [
                'missingFields' => \implode(', ', $missingFields),
            ]
        );
    }

    public static function invalidLoginState(): self
    {
        return new self(
            Response::HTTP_UNAUTHORIZED,
            self::SSO_LOGIN_INVALID_LOGIN_STATE,
            'Invalid login state'
        );
    }

    /**
     * @param array<int, string> $missingFields
     */
    public static function tokenNotValid(array $missingFields): self
    {
        return new self(
            Response::HTTP_UNAUTHORIZED,
            self::SSO_LOGIN_INVALID_TOKEN_RESPONSE,
            'Token not valid. Missing: {{ missingFields }}',
            [
                'missingFields' => \implode(', ', $missingFields),
            ]
        );
    }

    /**
     * @param array<int, string> $violations
     */
    public static function invalidIdTokenDataSet(array $violations): self
    {
        return new self(
            Response::HTTP_UNAUTHORIZED,
            self::SSO_LOGIN_INVALID_ID_TOKEN_DATA_SET,
            'ID-Token not valid: {{ missingFields }}',
            [
                'missingFields' => \implode(', ', $violations),
            ]
        );
    }

    public static function noCodeProvided(): self
    {
        return new self(
            Response::HTTP_UNAUTHORIZED,
            self::SSO_LOGIN_INVALID_REQUEST_NO_CODE_PROVIDED,
            'Invalid request. Request does not provide a code',
        );
    }

    public static function publicKeyNotFound(): self
    {
        return new self(
            Response::HTTP_UNAUTHORIZED,
            self::SSO_LOGIN_PUBLIC_KEY_NOT_FOUND,
            'Public key not found',
        );
    }

    public static function invalidIdToken(): self
    {
        return new self(
            Response::HTTP_UNAUTHORIZED,
            self::SSO_LOGIN_INVALID_ID_TOKEN,
            'The id token is invalid',
        );
    }

    /**
     * @param array<int, string> $violations
     */
    public static function invalidRefreshOrAccessToken(array $violations): self
    {
        return new self(
            Response::HTTP_UNAUTHORIZED,
            self::SSO_LOGIN_INVALID_REFRESH_OR_ACCESS_TOKEN,
            'Invalid user Access or refresh token: {{ missingFields }}',
            [
                'missingFields' => \implode(', ', $violations),
            ]
        );
    }

    public static function invalidPublicKey(string $response): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::SSO_LOGIN_INVALID_PUBLIC_KEY,
            'Got invalid JSON public keys. Got: {{ response }}',
            [
                'response' => $response,
            ]
        );
    }

    public static function negativeTimeToLive(): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::SSO_LOGIN_NEGATIVE_TIME_TO_LIVE,
            'Token time to live should not be negative',
        );
    }

    public static function tokenNotFound(): self
    {
        return new self(
            Response::HTTP_UNAUTHORIZED,
            self::SSO_LOGIN_TOKEN_NOT_FOUND,
            'Cannot get token from user.',
        );
    }

    public static function oauth2ProviderNotAvailable(string $technicalName): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::OAUTH2_PROVIDER_NOT_AVAILABLE,
            'The OAuth2 provider "{{ technicalName }}" is not available.',
            ['technicalName' => $technicalName]
        );
    }

    public static function oauth2ProviderNotConfigured(string $technicalName): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::OAUTH2_PROVIDER_NOT_CONFIGURED,
            'The OAuth2 provider "{{ technicalName }}" is enabled but not configured.',
            ['technicalName' => $technicalName]
        );
    }

    public static function oauth2ChannelDisabled(string $technicalName): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::OAUTH2_CHANNEL_DISABLED,
            'The OAuth2 channel "{{ technicalName }}" is not enabled.',
            ['technicalName' => $technicalName]
        );
    }

    public static function oauth2InvalidState(): self
    {
        return new self(
            Response::HTTP_UNAUTHORIZED,
            self::OAUTH2_INVALID_STATE,
            'The OAuth2 state does not match, the session expired or the flow was started with another provider.'
        );
    }

    public static function oauth2AuthorizationFailed(string $technicalName): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::OAUTH2_AUTHORIZATION_FAILED,
            'The authorization at "{{ technicalName }}" failed.',
            ['technicalName' => $technicalName],
        );
    }

    public static function oauth2IdentityMissing(string $technicalName): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::OAUTH2_IDENTITY_MISSING,
            '"{{ technicalName }}" did not return a user id.',
            ['technicalName' => $technicalName]
        );
    }

    public static function oauth2IdentityNotBound(string $technicalName): self
    {
        return new self(
            Response::HTTP_UNAUTHORIZED,
            self::OAUTH2_IDENTITY_NOT_BOUND,
            'The "{{ technicalName }}" account is not bound to an account of this shop.',
            ['technicalName' => $technicalName]
        );
    }

    public static function oauth2IdentityAlreadyBound(string $technicalName): self
    {
        return new self(
            Response::HTTP_CONFLICT,
            self::OAUTH2_IDENTITY_ALREADY_BOUND,
            'The "{{ technicalName }}" account is already bound to another account of this shop.',
            ['technicalName' => $technicalName]
        );
    }

    public static function oauth2MissingVerifiedEmail(string $technicalName): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::OAUTH2_MISSING_VERIFIED_EMAIL,
            '"{{ technicalName }}" did not return a verified email address, please register with an email address first.',
            ['technicalName' => $technicalName]
        );
    }

    public static function oauth2InvalidResourceOwnerResponse(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::OAUTH2_INVALID_RESOURCE_OWNER_RESPONSE,
            'The platform returned an invalid resource owner response, expected JSON.'
        );
    }

    public static function oauth2InvalidLoginCode(): self
    {
        return new self(
            Response::HTTP_UNAUTHORIZED,
            self::OAUTH2_INVALID_LOGIN_CODE,
            'The login code is unknown, already used or expired.'
        );
    }

    public static function oauth2AdminUserNotFound(): self
    {
        return new self(
            Response::HTTP_UNAUTHORIZED,
            self::OAUTH2_ADMIN_USER_NOT_FOUND,
            'The user of the login code does not exist or is not active.'
        );
    }
}
