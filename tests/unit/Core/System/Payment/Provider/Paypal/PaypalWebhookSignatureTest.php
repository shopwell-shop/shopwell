<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Provider\Paypal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\Paypal\PaypalApi;
use Shopwell\Core\System\Payment\Provider\Paypal\PaypalWebhookSignature;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * The signature covers `transmissionId|transmissionTime|webhookId|crc32(body)` and is verified with a
 * certificate paypal publishes. It lives outside the api because a payout notification is verified
 * before any merchant configuration is resolved.
 *
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaypalWebhookSignature::class)]
class PaypalWebhookSignatureTest extends TestCase
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

    public function testAnEmptyWebhookIdCannotVerifyAnything(): void
    {
        $this->expectExceptionObject(PaymentException::missingCredentials(PaypalApi::PROVIDER, PaypalApi::WEBHOOK_ID));

        (new PaypalWebhookSignature(new MockHttpClient(new MockResponse($this->publicKey))))->verify('', '{}', $this->headers());
    }

    /**
     * Without a client nothing can be fetched, and not verifying is the safe answer.
     */
    public function testWithoutAnHttpClientNothingIsVerified(): void
    {
        static::assertFalse((new PaypalWebhookSignature())->verify(self::WEBHOOK_ID, '{}', $this->headers()));
    }

    public function testANotificationWithoutTheSignatureHeadersIsRejected(): void
    {
        static::assertFalse($this->signature()->verify(self::WEBHOOK_ID, '{}', []));
    }

    /**
     * A notification may point at any host, so a url that is not paypal is never called - otherwise
     * the endpoint could be made to fetch an arbitrary url.
     */
    public function testACertificateFromAnotherHostIsNotFetched(): void
    {
        $called = false;
        $publicKey = $this->publicKey;
        $httpClient = new MockHttpClient(static function () use (&$called, $publicKey): MockResponse {
            $called = true;

            return new MockResponse($publicKey);
        });

        static::assertFalse((new PaypalWebhookSignature($httpClient))->verify(
            self::WEBHOOK_ID,
            '{}',
            $this->headers(certUrl: 'http://api.paypal.com/v1/notifications/certs/CERT-1')
        ));
        static::assertFalse($called);
    }

    public function testACertificateThatCannotBeReadIsRejected(): void
    {
        $signature = new PaypalWebhookSignature(new MockHttpClient(new MockResponse('not a certificate')));

        static::assertFalse($signature->verify(self::WEBHOOK_ID, '{}', $this->headers(signature: $this->sign('{}'))));
    }

    public function testAFailedDownloadIsRejected(): void
    {
        $httpClient = new MockHttpClient(new MockResponse('', ['http_code' => 500]));

        static::assertFalse((new PaypalWebhookSignature($httpClient))->verify(
            self::WEBHOOK_ID,
            '{}',
            $this->headers(signature: $this->sign('{}'))
        ));
    }

    public function testABrokenSignatureIsRejected(): void
    {
        static::assertFalse($this->signature()->verify(self::WEBHOOK_ID, '{}', $this->headers(signature: 'not base64 !!')));
    }

    public function testASignatureOfAnotherKeyIsRejected(): void
    {
        $other = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => \OPENSSL_KEYTYPE_RSA]);
        static::assertNotFalse($other);
        $otherPrivateKey = '';
        openssl_pkey_export($other, $otherPrivateKey);

        static::assertFalse($this->signature()->verify(self::WEBHOOK_ID, '{}', $this->headers(signature: $this->sign('{}', $otherPrivateKey))));
    }

    public function testATamperedBodyIsRejected(): void
    {
        static::assertFalse($this->signature()->verify(self::WEBHOOK_ID, '{"id":"evt-2"}', $this->headers(signature: $this->sign('{"id":"evt-1"}'))));
    }

    public function testASignatureForAnotherWebhookIdIsRejected(): void
    {
        static::assertFalse($this->signature()->verify('WH-OTHER', '{}', $this->headers(signature: $this->sign('{}'))));
    }

    public function testASignatureIsAccepted(): void
    {
        static::assertTrue($this->signature()->verify(self::WEBHOOK_ID, '{"id":"evt-1"}', $this->headers(signature: $this->sign('{"id":"evt-1"}'))));
    }

    /**
     * Paypal announces the algorithm it signed with, and all three it uses have to be understood -
     * a hard coded sha256 would reject the older transmissions.
     *
     * @param int $algorithm the openssl algorithm the test signs with
     */
    #[DataProvider('algorithmProvider')]
    public function testTheAnnouncedAlgorithmIsUsed(string $header, int $algorithm): void
    {
        $signature = $this->sign('{}', null, $algorithm);

        static::assertTrue($this->signature()->verify(self::WEBHOOK_ID, '{}', $this->headers(signature: $signature, algorithm: $header)));
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function algorithmProvider(): iterable
    {
        yield 'sha1' => ['SHA1withRSA', \OPENSSL_ALGO_SHA1];
        yield 'sha256' => ['SHA256withRSA', \OPENSSL_ALGO_SHA256];
        yield 'sha512' => ['SHA512withRSA', \OPENSSL_ALGO_SHA512];
    }

    /**
     * The certificate is fetched once per url and reused: a batch of notifications of the same
     * transmission must not download it again.
     */
    public function testTheCertificateIsFetchedOnlyOnce(): void
    {
        $requests = 0;
        $httpClient = new MockHttpClient(function () use (&$requests): MockResponse {
            ++$requests;

            return new MockResponse($this->publicKey);
        });

        $signature = new PaypalWebhookSignature($httpClient);
        $headers = $this->headers(signature: $this->sign('{}'));

        static::assertTrue($signature->verify(self::WEBHOOK_ID, '{}', $headers));
        static::assertTrue($signature->verify(self::WEBHOOK_ID, '{}', $headers));
        static::assertSame(1, $requests);
    }

    private function signature(): PaypalWebhookSignature
    {
        return new PaypalWebhookSignature(new MockHttpClient(new MockResponse($this->publicKey)));
    }

    /**
     * @return array<string, string>
     */
    private function headers(?string $signature = null, string $algorithm = 'SHA256withRSA', string $certUrl = self::CERT_URL): array
    {
        return [
            PaypalApi::HEADER_TRANSMISSION_ID => self::TRANSMISSION_ID,
            PaypalApi::HEADER_TRANSMISSION_TIME => self::TRANSMISSION_TIME,
            PaypalApi::HEADER_TRANSMISSION_SIG => $signature ?? $this->sign('{}'),
            PaypalApi::HEADER_CERT_URL => $certUrl,
            PaypalApi::HEADER_AUTH_ALGO => $algorithm,
        ];
    }

    private function sign(string $body, ?string $privateKey = null, int $algorithm = \OPENSSL_ALGO_SHA256): string
    {
        $message = implode('|', [
            self::TRANSMISSION_ID,
            self::TRANSMISSION_TIME,
            self::WEBHOOK_ID,
            (string) crc32($body),
        ]);

        $signature = '';
        openssl_sign($message, $signature, $privateKey ?? $this->privateKey, $algorithm);

        return base64_encode($signature);
    }
}
