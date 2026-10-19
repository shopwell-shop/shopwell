<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Provider\Paypal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\Paypal\PaypalApi;
use Shopwell\Core\System\Payment\Provider\Paypal\PaypalTransferNotificationVerifier;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Paypal signs `transmissionId|transmissionTime|webhookId|crc32(body)` with a certificate it publishes
 * per transmission. The certificate is downloaded, so the test serves it from a mock client and signs
 * with the matching key.
 *
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaypalTransferNotificationVerifier::class)]
class PaypalTransferNotificationVerifierTest extends TestCase
{
    private const WEBHOOK_ID = 'WH-TEST-1';

    private const CERT_URL = 'https://api.paypal.com/v1/notifications/certs/CERT-1';

    private const TRANSMISSION_ID = 'transmission-1';

    private const TRANSMISSION_TIME = '2024-01-01T00:00:00Z';

    private string $privateKey;

    private string $publicKey;

    protected function setUp(): void
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => \OPENSSL_KEYTYPE_RSA]);
        static::assertNotFalse($key);

        $privateKey = '';
        openssl_pkey_export($key, $privateKey);
        $details = openssl_pkey_get_details($key);
        static::assertIsArray($details);

        $this->privateKey = $privateKey;
        $this->publicKey = $details['key'];
    }

    public function testProviderIsPaypal(): void
    {
        static::assertSame(PaypalApi::PROVIDER, (new PaypalTransferNotificationVerifier())->provider());
    }

    public function testANotificationWithoutAWebhookIdThrows(): void
    {
        $this->expectExceptionObject(PaymentException::missingCredentials(PaypalApi::PROVIDER, PaypalApi::WEBHOOK_ID));

        $this->verifier()->verify([], '{}', $this->headers());
    }

    public function testANotificationWithoutTheSignatureHeadersIsRejected(): void
    {
        static::assertFalse($this->verifier()->verify($this->credentials(), '{}', []));
    }

    public function testAnEmptyBodyWithoutASignatureIsRejected(): void
    {
        static::assertFalse($this->verifier()->verify($this->credentials(), '', []));
    }

    /**
     * A notification may point at any host, so only paypal certificates are fetched - otherwise the
     * endpoint could be made to call an arbitrary url.
     */
    public function testACertificateFromAnotherHostIsNotFetched(): void
    {
        $headers = $this->headers('https://evil.example/cert.pem');

        static::assertFalse($this->verifier()->verify($this->credentials(), '{"id":"evt-1"}', $headers));
    }

    public function testACertificateThatCannotBeReadIsRejected(): void
    {
        $httpClient = new MockHttpClient(new MockResponse('not a certificate'));

        static::assertFalse($this->verifier($httpClient)->verify($this->credentials(), '{"id":"evt-1"}', $this->headers()));
    }

    public function testASignatureOfAnotherKeyIsRejected(): void
    {
        $other = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => \OPENSSL_KEYTYPE_RSA]);
        static::assertNotFalse($other);
        $otherPrivateKey = '';
        openssl_pkey_export($other, $otherPrivateKey);

        $body = '{"id":"evt-1"}';
        $signature = $this->sign($body, self::WEBHOOK_ID, $otherPrivateKey);

        static::assertFalse($this->verifier()->verify($this->credentials(), $body, $this->headers(signature: $signature)));
    }

    public function testATamperedBodyIsRejected(): void
    {
        $signature = $this->sign('{"id":"evt-1"}', self::WEBHOOK_ID);

        static::assertFalse($this->verifier()->verify($this->credentials(), '{"id":"evt-2"}', $this->headers(signature: $signature)));
    }

    public function testASignatureForAnotherWebhookIdIsRejected(): void
    {
        $signature = $this->sign('{"id":"evt-1"}', 'WH-OTHER');

        static::assertFalse($this->verifier()->verify($this->credentials(), '{"id":"evt-1"}', $this->headers(signature: $signature)));
    }

    public function testASignedNotificationIsAccepted(): void
    {
        $body = '{"id":"evt-1","event_type":"PAYMENT.PAYOUTS-ITEM.SUCCEEDED"}';
        $signature = $this->sign($body, self::WEBHOOK_ID);

        static::assertTrue($this->verifier()->verify($this->credentials(), $body, $this->headers(signature: $signature)));
    }

    public function testThePayoutNumberIsTheSenderItemId(): void
    {
        $body = json_encode([
            'id' => 'evt-1',
            'event_type' => 'PAYMENT.PAYOUTS-ITEM.SUCCEEDED',
            'resource' => [
                'transaction_status' => 'SUCCESS',
                'payout_item' => ['sender_item_id' => 'PO-1'],
            ],
        ], \JSON_THROW_ON_ERROR);

        static::assertSame('PO-1', (new PaypalTransferNotificationVerifier())->outNoOf([], $body, []));
    }

    public function testANotificationWithoutASenderItemIdHasNoPayoutNumber(): void
    {
        static::assertNull((new PaypalTransferNotificationVerifier())->outNoOf([], '{"id":"evt-1","resource":{}}', []));
    }

    public function testABrokenBodyHasNoPayoutNumber(): void
    {
        static::assertNull((new PaypalTransferNotificationVerifier())->outNoOf([], 'not-json', []));
    }

    public function testPaypalIsAcknowledgedWithAnEmptyJsonBody(): void
    {
        $ack = (new PaypalTransferNotificationVerifier())->acknowledge();

        static::assertSame('', $ack->body);
        static::assertSame('application/json', $ack->contentType);
    }

    /**
     * @return array<string, string>
     */
    private function credentials(): array
    {
        return [PaypalApi::WEBHOOK_ID => self::WEBHOOK_ID];
    }

    private function verifier(?MockHttpClient $httpClient = null): PaypalTransferNotificationVerifier
    {
        return new PaypalTransferNotificationVerifier(
            $httpClient ?? new MockHttpClient(new MockResponse($this->publicKey))
        );
    }

    /**
     * @return array<string, string>
     */
    private function headers(string $certUrl = self::CERT_URL, ?string $signature = null): array
    {
        return [
            PaypalApi::HEADER_TRANSMISSION_ID => self::TRANSMISSION_ID,
            PaypalApi::HEADER_TRANSMISSION_TIME => self::TRANSMISSION_TIME,
            PaypalApi::HEADER_TRANSMISSION_SIG => $signature ?? $this->sign('{}', self::WEBHOOK_ID),
            PaypalApi::HEADER_CERT_URL => $certUrl,
            PaypalApi::HEADER_AUTH_ALGO => 'SHA256withRSA',
        ];
    }

    private function sign(string $body, string $webhookId, ?string $privateKey = null): string
    {
        $message = implode('|', [
            self::TRANSMISSION_ID,
            self::TRANSMISSION_TIME,
            $webhookId,
            (string) crc32($body),
        ]);

        $signature = '';
        openssl_sign($message, $signature, $privateKey ?? $this->privateKey, \OPENSSL_ALGO_SHA256);

        return base64_encode($signature);
    }
}
