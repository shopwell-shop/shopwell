<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Provider\Stripe;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Notification\Struct\PaymentNotificationAck;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Transfer\Notification\PaymentTransferNotificationVerifierInterface;
use Stripe\Exception\SignatureVerificationException;
use Stripe\WebhookSignature;

/**
 * Verifies the payout events of stripe, `payout.paid`, `payout.failed` and friends.
 *
 * Stripe signs the raw body with the signing secret of the webhook endpoint, so the body can be read
 * without decrypting anything. The payout number of the platform is written into the metadata of the
 * transfer and the payout when the submission is built, and stripe carries metadata from the creation
 * of an object to its notification - that is where it is read back from.
 *
 * The endpoint is account level: stripe notifies what happened on the account, not what happened to
 * one payout, which is why the url names the app and the payout is taken from the body.
 *
 * @internal
 */
#[Package('framework')]
final class StripeTransferNotificationVerifier implements PaymentTransferNotificationVerifierInterface
{
    /**
     * The metadata key the submission writes the business number into.
     */
    private const OUT_NO = 'out_no';

    public function provider(): string
    {
        return StripeApi::PROVIDER;
    }

    public function verify(array $credentials, string $rawBody, array $headers): bool
    {
        $signature = self::headerOf($headers, StripeNotificationExtractor::HEADER_SIGNATURE);

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

    public function outNoOf(array $credentials, string $rawBody, array $headers): ?string
    {
        $event = json_decode($rawBody, true);

        if (!\is_array($event)) {
            return null;
        }

        $object = \is_array($event['data'] ?? null) && \is_array($event['data']['object'] ?? null)
            ? $event['data']['object']
            : [];

        $metadata = \is_array($object['metadata'] ?? null) ? $object['metadata'] : [];

        $outNo = $metadata[self::OUT_NO] ?? null;

        return \is_scalar($outNo) && (string) $outNo !== '' ? (string) $outNo : null;
    }

    /**
     * Stripe repeats a notification until it is acknowledged with a 2xx status, the body is ignored.
     */
    public function acknowledge(): PaymentNotificationAck
    {
        return new PaymentNotificationAck('', 'application/json');
    }

    /**
     * The signature header stripe signs the body with, `t=...,v1=...`.
     *
     * @param array<string, string|list<string>> $headers
     */
    private static function headerOf(array $headers, string $name): string
    {
        foreach ($headers as $key => $value) {
            if (strtolower(str_replace('_', '-', (string) $key)) !== $name) {
                continue;
            }

            if (\is_array($value)) {
                $value = reset($value);
            }

            return \is_scalar($value) ? (string) $value : '';
        }

        return '';
    }
}
