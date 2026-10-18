<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Provider\Paypal;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\PaymentException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Verifies the transmission signature of a paypal webhook.
 *
 * Paypal signs `transmissionId|transmissionTime|webhookId|crc32(rawBody)` with a certificate it
 * publishes, and the notification names the certificate in the `Paypal-Cert-Url` header. The
 * certificate is downloaded on demand - paypal rotates them, so there is no certificate of our own to
 * configure - and only from a paypal host, because a notification may point at any url.
 *
 * It lives next to the api and not inside it because the payout notification is verified before any
 * merchant configuration is resolved: it needs the webhook id and nothing else, and the query side of
 * the api must keep reading the very same signature.
 *
 * @internal
 */
#[Package('framework')]
final class PaypalWebhookSignature
{
    /**
     * The algorithms paypal uses for its notification signatures.
     */
    private const AUTH_ALGORITHMS = [
        'SHA1WITHRSA' => \OPENSSL_ALGO_SHA1,
        'SHA256WITHRSA' => \OPENSSL_ALGO_SHA256,
        'SHA512WITHRSA' => \OPENSSL_ALGO_SHA512,
    ];

    /**
     * @var array<string, \OpenSSLAsymmetricKey|null>
     */
    private array $certificates = [];

    /**
     * @param HttpClientInterface|null $httpClient fetches the certificate the notification was signed
     *                                             with; without one nothing can be verified, which is
     *                                             the safe answer
     */
    public function __construct(private readonly ?HttpClientInterface $httpClient = null)
    {
    }

    /**
     * Whether the notification carries everything a signature needs. A caller that answers `false`
     * without verifying - the payment notification does, so a configuration problem does not fail an
     * order that can still be reconciled by a query - asks this first.
     *
     * @param array<string, string|list<string>> $headers
     */
    public static function hasSignatureHeaders(array $headers): bool
    {
        return self::headerOf($headers, PaypalApi::HEADER_TRANSMISSION_ID) !== ''
            && self::headerOf($headers, PaypalApi::HEADER_TRANSMISSION_TIME) !== ''
            && self::headerOf($headers, PaypalApi::HEADER_TRANSMISSION_SIG) !== ''
            && self::headerOf($headers, PaypalApi::HEADER_CERT_URL) !== '';
    }

    /**
     * @param array<string, string|list<string>> $headers
     *
     * @throws PaymentException when the webhook id is not configured
     */
    public function verify(string $webhookId, string $rawBody, array $headers): bool
    {
        if ($webhookId === '') {
            throw PaymentException::missingCredentials(PaypalApi::PROVIDER, PaypalApi::WEBHOOK_ID);
        }

        if (!self::hasSignatureHeaders($headers)) {
            return false;
        }

        $transmissionId = self::headerOf($headers, PaypalApi::HEADER_TRANSMISSION_ID);
        $transmissionTime = self::headerOf($headers, PaypalApi::HEADER_TRANSMISSION_TIME);
        $signature = self::headerOf($headers, PaypalApi::HEADER_TRANSMISSION_SIG);
        $certificateUrl = self::headerOf($headers, PaypalApi::HEADER_CERT_URL);

        $certificate = $this->certificateOf($certificateUrl);

        if ($certificate === null) {
            return false;
        }

        $decoded = base64_decode($signature, true);

        if ($decoded === false) {
            return false;
        }

        $message = implode('|', [
            $transmissionId,
            $transmissionTime,
            $webhookId,
            (string) crc32($rawBody),
        ]);

        try {
            return openssl_verify($message, $decoded, $certificate, $this->authAlgorithm($headers)) === 1;
        } catch (\Throwable) {
            return false;
        }
    }

    private function certificateOf(string $url): ?\OpenSSLAsymmetricKey
    {
        if (\array_key_exists($url, $this->certificates)) {
            return $this->certificates[$url];
        }

        if (!$this->isTrustedCertUrl($url) || $this->httpClient === null) {
            return $this->certificates[$url] = null;
        }

        try {
            $pem = $this->httpClient->request('GET', $url, ['timeout' => 10])->getContent();
        } catch (\Throwable) {
            return $this->certificates[$url] = null;
        }

        $key = openssl_pkey_get_public($pem);

        return $this->certificates[$url] = ($key === false ? null : $key);
    }

    /**
     * A notification may point at any host, so only paypal is allowed to be called.
     */
    private function isTrustedCertUrl(string $url): bool
    {
        if (parse_url($url, \PHP_URL_SCHEME) !== 'https') {
            return false;
        }

        $host = parse_url($url, \PHP_URL_HOST);

        if (!\is_string($host) || $host === '') {
            return false;
        }

        return str_ends_with($host, '.paypal.com') || str_ends_with($host, '.paypalobjects.com');
    }

    /**
     * @param array<string, string|list<string>> $headers
     */
    private function authAlgorithm(array $headers): int
    {
        $algorithm = strtoupper(trim(self::headerOf($headers, PaypalApi::HEADER_AUTH_ALGO)));

        return self::AUTH_ALGORITHMS[$algorithm] ?? \OPENSSL_ALGO_SHA256;
    }

    /**
     * @param array<string, string|list<string>> $headers
     */
    private static function headerOf(array $headers, string $name): string
    {
        foreach ($headers as $key => $value) {
            if (strtolower((string) $key) !== $name) {
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
