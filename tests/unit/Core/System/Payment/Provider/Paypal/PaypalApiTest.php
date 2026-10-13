<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Provider\Paypal;

use PaypalServerSdkLib\Environment;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\AbstractPaymentApi;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\Paypal\PaypalApi;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaypalApi::class)]
class PaypalApiTest extends TestCase
{
    public function testExposesConfiguration(): void
    {
        $api = new PaypalApi($this->config(['client_id' => 'client-id']));

        static::assertSame('paypal', $api->provider());
        static::assertSame('paypal_checkout', $api->channel());
        static::assertSame('live', $api->mode());
    }

    public function testEnvironmentDefaultsToSandbox(): void
    {
        static::assertSame(Environment::SANDBOX, (new PaypalApi($this->config()))->environment());
    }

    public function testEnvironmentIsProductionForTheLiveMode(): void
    {
        static::assertSame(Environment::PRODUCTION, (new PaypalApi($this->config(['environment' => 'live'])))->environment());

        // the value is normalized before it is compared
        static::assertSame(Environment::PRODUCTION, (new PaypalApi($this->config(['environment' => 'LIVE'])))->environment());
        static::assertSame(Environment::SANDBOX, (new PaypalApi($this->config(['environment' => 'production'])))->environment());
    }

    public function testWebhookIdReturnsCredential(): void
    {
        $api = new PaypalApi($this->config(['webhook_id' => 'WH-1']));

        static::assertSame('WH-1', $api->webhookId());
    }

    public function testWebhookIdThrowsWhenTheCredentialIsMissing(): void
    {
        $api = new PaypalApi($this->config());

        $this->expectExceptionObject(PaymentException::missingCredentials('paypal', PaypalApi::WEBHOOK_ID));

        $api->webhookId();
    }

    public function testBrandNameFallsBackToAnEmptyString(): void
    {
        static::assertSame('', (new PaypalApi($this->config()))->brandName());
        static::assertSame('Shopwell', (new PaypalApi($this->config(['brand_name' => 'Shopwell'])))->brandName());
    }

    public function testAmountFormatsTheMinorUnitAsTheDecimalStringTheChannelExpects(): void
    {
        $api = new PaypalApi($this->config());

        static::assertSame('1.00', $api->amount(100, 'CNY'));
        static::assertSame('0.05', $api->amount(5, 'USD'));
        static::assertSame('-1.00', $api->amount(-100, 'CNY'));
        // the currency without fractional digits
        static::assertSame('100', $api->amount(100, 'JPY'));
    }

    public function testVerifyWebhookSignatureReturnsFalseWhenHeadersAreMissing(): void
    {
        $api = new PaypalApi($this->config(['webhook_id' => 'WH-1']));

        static::assertFalse($api->verifyWebhookSignature('{}', []));
        static::assertFalse($api->verifyWebhookSignature('{}', [PaypalApi::HEADER_TRANSMISSION_ID => 'transmission']));
    }

    public function testVerifyWebhookSignatureReturnsFalseForAnUntrustedCertificateUrl(): void
    {
        $api = new PaypalApi($this->config(['webhook_id' => 'WH-1']), $this->stubHttpClient('a-certificate'));

        static::assertFalse($api->verifyWebhookSignature('{}', $this->signatureHeaders('https://evil.example.com/cert.pem')));
        static::assertFalse($api->verifyWebhookSignature('{}', $this->signatureHeaders('http://api.paypal.com/cert.pem')));
    }

    public function testVerifyWebhookSignatureReturnsFalseWithoutAnHttpClient(): void
    {
        $api = new PaypalApi($this->config(['webhook_id' => 'WH-1']));

        static::assertFalse($api->verifyWebhookSignature('{}', $this->signatureHeaders('https://api.paypal.com/cert.pem')));
    }

    public function testVerifyWebhookSignatureReturnsFalseWhenTheSignatureIsBroken(): void
    {
        $api = new PaypalApi($this->config(['webhook_id' => 'WH-1']), $this->stubHttpClient('a-certificate'));

        static::assertFalse($api->verifyWebhookSignature('{}', $this->signatureHeaders('https://api.paypal.com/cert.pem', '###not-base64###')));
    }

    public function testVerifyWebhookSignatureVerifiesASignatureOfPaypal(): void
    {
        [$privateKey, $publicKey] = $this->keyPair();
        $api = new PaypalApi($this->config(['webhook_id' => 'WH-1']), $this->stubHttpClient($publicKey));

        $rawBody = '{"event_type":"PAYMENT.CAPTURE.COMPLETED"}';
        $headers = [
            PaypalApi::HEADER_TRANSMISSION_ID => 'transmission-id',
            PaypalApi::HEADER_TRANSMISSION_TIME => '2026-01-01T00:00:00Z',
            PaypalApi::HEADER_AUTH_ALGO => 'SHA256WITHRSA',
            PaypalApi::HEADER_CERT_URL => 'https://api.paypal.com/v1/notifications/certs/CERT-1',
            PaypalApi::HEADER_TRANSMISSION_SIG => $this->sign('transmission-id|2026-01-01T00:00:00Z|WH-1|' . crc32($rawBody), $privateKey, \OPENSSL_ALGO_SHA256),
        ];

        static::assertTrue($api->verifyWebhookSignature($rawBody, $headers));
        // a different body cannot match the signature
        static::assertFalse($api->verifyWebhookSignature('{"event_type":"OTHER"}', $headers));
    }

    public function testVerifyWebhookSignatureSupportsTheSha512Algorithm(): void
    {
        [$privateKey, $publicKey] = $this->keyPair();
        $api = new PaypalApi($this->config(['webhook_id' => 'WH-1']), $this->stubHttpClient($publicKey));

        $rawBody = '{}';
        $headers = [
            PaypalApi::HEADER_TRANSMISSION_ID => 'transmission-id',
            PaypalApi::HEADER_TRANSMISSION_TIME => '2026-01-01T00:00:00Z',
            PaypalApi::HEADER_AUTH_ALGO => 'SHA512WITHRSA',
            PaypalApi::HEADER_CERT_URL => 'https://api.paypal.com/cert.pem',
            PaypalApi::HEADER_TRANSMISSION_SIG => $this->sign('transmission-id|2026-01-01T00:00:00Z|WH-1|' . crc32($rawBody), $privateKey, \OPENSSL_ALGO_SHA512),
        ];

        static::assertTrue($api->verifyWebhookSignature($rawBody, $headers));
    }

    public function testVerifyWebhookSignatureFetchesTheCertificateOnlyOnce(): void
    {
        [$privateKey, $publicKey] = $this->keyPair();

        $response = static::createStub(ResponseInterface::class);
        $response->method('getContent')->willReturn($publicKey);

        $httpClient = static::createMock(HttpClientInterface::class);
        $httpClient->expects($this->once())->method('request')->willReturn($response);

        $api = new PaypalApi($this->config(['webhook_id' => 'WH-1']), $httpClient);

        $rawBody = '{}';
        $headers = [
            PaypalApi::HEADER_TRANSMISSION_ID => 'transmission-id',
            PaypalApi::HEADER_TRANSMISSION_TIME => '2026-01-01T00:00:00Z',
            PaypalApi::HEADER_AUTH_ALGO => 'SHA256WITHRSA',
            PaypalApi::HEADER_CERT_URL => 'https://api.paypal.com/cert.pem',
            PaypalApi::HEADER_TRANSMISSION_SIG => $this->sign('transmission-id|2026-01-01T00:00:00Z|WH-1|' . crc32($rawBody), $privateKey, \OPENSSL_ALGO_SHA256),
        ];

        static::assertTrue($api->verifyWebhookSignature($rawBody, $headers));
        static::assertTrue($api->verifyWebhookSignature($rawBody, $headers));
    }

    public function testClientBuildsTheSdkClient(): void
    {
        $api = new PaypalApi($this->config(['client_id' => 'client-id', 'client_secret' => 'client-secret']));

        $client = $api->client();

        static::assertSame($client, $api->client());
    }

    public function testClientThrowsWhenCredentialsAreMissing(): void
    {
        $api = new PaypalApi($this->config());

        $this->expectExceptionObject(PaymentException::missingCredentials('paypal', PaypalApi::CLIENT_ID));

        $api->client();
    }

    /**
     * @return array<string, string>
     */
    private function signatureHeaders(string $certificateUrl, string $signature = 'c2lnbmF0dXJl'): array
    {
        return [
            PaypalApi::HEADER_TRANSMISSION_ID => 'transmission-id',
            PaypalApi::HEADER_TRANSMISSION_TIME => '2026-01-01T00:00:00Z',
            PaypalApi::HEADER_TRANSMISSION_SIG => $signature,
            PaypalApi::HEADER_CERT_URL => $certificateUrl,
        ];
    }

    private function sign(string $message, string $privateKey, int $algorithm): string
    {
        openssl_sign($message, $signature, $privateKey, $algorithm);

        return base64_encode($signature);
    }

    /**
     * @return array{string, string}
     */
    private function keyPair(): array
    {
        $resource = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => \OPENSSL_KEYTYPE_RSA]);
        static::assertNotFalse($resource);

        openssl_pkey_export($resource, $privateKey);
        $details = openssl_pkey_get_details($resource);
        static::assertIsArray($details);

        return [(string) $privateKey, (string) $details['key']];
    }

    private function stubHttpClient(string $certificate): HttpClientInterface
    {
        $response = static::createStub(ResponseInterface::class);
        $response->method('getContent')->willReturn($certificate);

        $httpClient = static::createStub(HttpClientInterface::class);
        $httpClient->method('request')->willReturn($response);

        return $httpClient;
    }

    /**
     * @param array<string, mixed> $credentials
     *
     * @return array<string, mixed>
     */
    private function config(array $credentials = []): array
    {
        return [
            AbstractPaymentApi::CONFIG_PROVIDER => 'paypal',
            AbstractPaymentApi::CONFIG_CHANNEL => 'paypal_checkout',
            AbstractPaymentApi::CONFIG_MODE => 'live',
            AbstractPaymentApi::CONFIG_CREDENTIALS => $credentials,
            AbstractPaymentApi::CONFIG_PARAMS => [],
            AbstractPaymentApi::CONFIG_SETTINGS => [],
            AbstractPaymentApi::CONFIG_RUNTIME_PARAMS => [],
            AbstractPaymentApi::CONFIG_NOTIFY_URL => 'https://example.com/notify',
            AbstractPaymentApi::CONFIG_RETURN_URL => 'https://example.com/return',
        ];
    }
}
