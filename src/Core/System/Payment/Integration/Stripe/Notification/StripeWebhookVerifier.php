<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Integration\Stripe\Notification;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Integration\Stripe\Api\StripeApi;
use Shopwell\Core\System\Payment\PaymentException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\WebhookSignature;

/**
 * Proves that a notification was sent by stripe: the raw body is signed with the signing secret of the
 * webhook endpoint, so an unsigned body confirms nothing - and there is nothing to decrypt.
 *
 * The payment notification ({@see \Shopwell\Core\System\Payment\Integration\Stripe\Api\StripeApi::verifyWebhookSignature()})
 * and the payout notification ({@see StripeTransferNotificationVerifier}) verify the very same way, so
 * the call and the exception mapping live here exactly once.
 *
 * @internal
 */
#[Package('framework')]
final class StripeWebhookVerifier
{
    private function __construct()
    {
    }

    /**
     * @param array<string, mixed> $credentials
     * @param string $signature the value of the signature header, `t=...,v1=...`
     *
     * @throws PaymentException when the notification cannot be checked at all, e.g. without a secret
     */
    public static function verify(array $credentials, string $rawBody, string $signature): bool
    {
        if ($signature === '') {
            return false;
        }

        $secret = $credentials[StripeApi::WEBHOOK_SECRET] ?? null;

        if (!\is_string($secret) || $secret === '') {
            throw PaymentException::missingCredentials(StripeApi::PROVIDER, StripeApi::WEBHOOK_SECRET);
        }

        try {
            WebhookSignature::verifyHeader($rawBody, $signature, $secret);

            return true;
        } catch (SignatureVerificationException) {
            return false;
        } catch (\Throwable $exception) {
            throw PaymentException::notificationInvalid(StripeApi::PROVIDER, $exception->getMessage());
        }
    }
}
