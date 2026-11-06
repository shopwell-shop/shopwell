<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Exception;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\PaymentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
class PaymentAppException extends PaymentException
{
    public static function rateLimitExceeded(int $seconds): self
    {
        $exception = new self(
            Response::HTTP_TOO_MANY_REQUESTS,
            self::APP_RATE_LIMIT_EXCEEDED,
            'Too many payment requests, try again in {{ seconds }} seconds.',
            ['seconds' => $seconds],
        );
        $exception->setHeaders(['Retry-After' => (string) $seconds]);

        return $exception;
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

    /**
     * The tenant the platform itself collects through is seeded, not onboarded, and it cannot be
     * deleted: the shop would be left without a tenant to attribute platform payments to, and every
     * platform payment would lose its bookkeeping. It stays editable, because the merchant configures
     * the interfaces and channels of the shop on it.
     */
    public static function platformTenantNotDeletable(string $technicalName): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::PLATFORM_TENANT_READ_ONLY,
            'The payment app "{{ technicalName }}" is the tenant of the platform and cannot be deleted here.',
            ['technicalName' => $technicalName]
        );
    }
}
