<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Webhook;

use Psr\Clock\ClockInterface;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\App\PaymentCredentialCipher;
use Shopwell\Core\System\Payment\App\PaymentSignatureService;
use Shopwell\Core\System\Payment\Order\PaymentOrderEntity;

/**
 * Builds and signs the payload of an outbound webhook delivery.
 *
 * The contract with the app - which fields a delivery carries, how they are encoded and which
 * secret signs them - lives in this one place, so the dispatcher only has to care about queueing,
 * sending and retrying. The signature is the very same HMAC the api requests are signed with, an
 * app verifies it like any other call, see {@see PaymentSignatureService::verifyPayload()}.
 *
 * A delivery is replay protected by its own payload: the `delivery_id` is unique per delivery and
 * signed with the rest, and the `timestamp` bounds its freshness - an app that remembers the
 * delivery ids it saw, within a window of its choosing, rejects a replayed body even though its
 * signature is still valid. The id is the one the app also receives as the delivery header, so a
 * delivery is only ever processed once no matter how often the body is replayed.
 *
 * @internal
 */
#[Package('framework')]
final class PaymentWebhookSigner
{
    public function __construct(
        private readonly PaymentSignatureService $signatureService,
        private readonly PaymentCredentialCipher $cipher,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array{payload: array<string, mixed>, signature: string}
     */
    public function sign(string $deliveryId, PaymentOrderEntity $order, PaymentAppEntity $app, string $event, array $data): array
    {
        $payload = [
            'event' => $event,
            'delivery_id' => $deliveryId,
            'payment_no' => $order->paymentNo,
            'out_trade_no' => $order->outTradeNo,
            'attach' => $order->attach,
            'data' => $data,
            'timestamp' => $this->clock->now()->getTimestamp(),
        ];

        return [
            'payload' => $payload,
            'signature' => $this->signatureService->signPayload($this->cipher->decryptValue($app->appSecret), $this->encode($payload)),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function encode(array $payload): string
    {
        return (string) json_encode($payload, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
    }
}
