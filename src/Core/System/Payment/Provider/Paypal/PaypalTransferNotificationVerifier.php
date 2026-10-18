<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Provider\Paypal;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Notification\Struct\PaymentNotificationAck;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Transfer\Notification\PaymentTransferNotificationVerifierInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Verifies the payout webhooks of paypal, `PAYMENT.PAYOUTS-ITEM.*`.
 *
 * Paypal notifies the merchant account, not a single payout: the webhook id identifies the endpoint and
 * the signature is verified with the certificate paypal publishes for the transmission. The payout
 * number of the platform travels as the `sender_item_id` of the payout item - the platform sends the
 * batch id there, which is derived from the business number deterministically, so the payout can be
 * found again from the notification.
 *
 * @internal
 */
#[Package('framework')]
final class PaypalTransferNotificationVerifier implements PaymentTransferNotificationVerifierInterface
{
    /**
     * The id of the payout item, which is the batch id and therefore derived from the business number.
     */
    private const OUT_NO = 'sender_item_id';

    public function __construct(private readonly ?HttpClientInterface $httpClient = null)
    {
    }

    public function provider(): string
    {
        return PaypalApi::PROVIDER;
    }

    public function verify(array $credentials, string $rawBody, array $headers): bool
    {
        $webhookId = $credentials[PaypalApi::WEBHOOK_ID] ?? null;

        if (!\is_string($webhookId) || $webhookId === '') {
            throw PaymentException::missingCredentials(PaypalApi::PROVIDER, PaypalApi::WEBHOOK_ID);
        }

        return (new PaypalWebhookSignature($this->httpClient))->verify($webhookId, $rawBody, $headers);
    }

    public function outNoOf(array $credentials, string $rawBody, array $headers): ?string
    {
        $event = json_decode($rawBody, true);

        if (!\is_array($event)) {
            return null;
        }

        $resource = \is_array($event['resource'] ?? null) ? $event['resource'] : [];

        // the resource of `PAYMENT.PAYOUTS-ITEM.*` is the payout item, and the item is nested: the
        // number the platform sent is echoed back under `payout_item`, not next to `transaction_status`
        $item = \is_array($resource['payout_item'] ?? null) ? $resource['payout_item'] : [];

        $outNo = $item[self::OUT_NO] ?? null;

        return \is_scalar($outNo) && (string) $outNo !== '' ? (string) $outNo : null;
    }

    /**
     * Paypal repeats a notification until it is acknowledged with http 200, the body is ignored.
     */
    public function acknowledge(): PaymentNotificationAck
    {
        return new PaymentNotificationAck('', 'application/json');
    }
}
