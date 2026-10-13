<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Provider\Paypal;

use PaypalServerSdkLib\Authentication\ClientCredentialsAuthCredentialsBuilder;
use PaypalServerSdkLib\Environment;
use PaypalServerSdkLib\PaypalServerSdkClient;
use PaypalServerSdkLib\PaypalServerSdkClientBuilder;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\AbstractPaymentApi;
use Shopwell\Core\System\Payment\Gateway\Money;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * The paypal api of one payment.
 *
 * Paypal is a rest api only: an order is created, the payer approves it at paypal and the platform
 * captures it afterwards - either itself, or through the webhook of paypal. Refunds work on the
 * *capture* of a trade, which is why the capture id is the payment number of the channel here.
 *
 * The webhook is verified against the certificate paypal serves for the notification; the certificate
 * url is validated against paypal before it is fetched, so a forged notification cannot make the
 * platform call an arbitrary host.
 *
 * @internal
 */
#[Package('framework')]
final class PaypalApi extends AbstractPaymentApi
{
    final public const CLIENT_ID = 'client_id';
    final public const CLIENT_SECRET = 'client_secret';

    /**
     * `sandbox` or `live`, see {@see Environment}.
     */
    final public const ENVIRONMENT = 'environment';

    /**
     * The id of the webhook paypal notifies the platform with, part of the signed message.
     */
    final public const WEBHOOK_ID = 'webhook_id';

    final public const BRAND_NAME = 'brand_name';

    final public const DEFAULT_ENVIRONMENT = 'sandbox';

    /**
     * The headers paypal sends with a webhook.
     */
    final public const HEADER_TRANSMISSION_ID = 'paypal-transmission-id';
    final public const HEADER_TRANSMISSION_TIME = 'paypal-transmission-time';
    final public const HEADER_TRANSMISSION_SIG = 'paypal-transmission-sig';
    final public const HEADER_CERT_URL = 'paypal-cert-url';
    final public const HEADER_AUTH_ALGO = 'paypal-auth-algo';

    /**
     * The algorithms paypal uses for its notification signatures.
     */
    private const AUTH_ALGORITHMS = [
        'SHA1WITHRSA' => \OPENSSL_ALGO_SHA1,
        'SHA256WITHRSA' => \OPENSSL_ALGO_SHA256,
        'SHA512WITHRSA' => \OPENSSL_ALGO_SHA512,
    ];

    private ?PaypalServerSdkClient $client = null;

    /**
     * @var array<string, \OpenSSLAsymmetricKey|null>
     */
    private array $certificates = [];

    public function __construct(
        array $config,
        private readonly ?HttpClientInterface $httpClient = null,
    ) {
        parent::__construct($config);
    }

    public function client(): PaypalServerSdkClient
    {
        if ($this->client instanceof PaypalServerSdkClient) {
            return $this->client;
        }

        return $this->client = PaypalServerSdkClientBuilder::init()
            ->environment($this->environment())
            ->clientCredentialsAuthCredentials(
                ClientCredentialsAuthCredentialsBuilder::init(
                    $this->requireCredential(self::CLIENT_ID),
                    $this->requireCredential(self::CLIENT_SECRET)
                )
            )
            ->build();
    }

    public function environment(): string
    {
        return strtolower($this->credential(self::ENVIRONMENT, self::DEFAULT_ENVIRONMENT) ?? self::DEFAULT_ENVIRONMENT) === 'live'
            ? Environment::PRODUCTION
            : Environment::SANDBOX;
    }

    public function webhookId(): string
    {
        return $this->requireCredential(self::WEBHOOK_ID);
    }

    public function brandName(): string
    {
        return $this->credential(self::BRAND_NAME) ?? '';
    }

    /**
     * Paypal expects the amount as a decimal string, the platform stores it in the minor unit.
     */
    public function amount(int $minor, string $currency): string
    {
        return Money::toDecimal($minor, $currency);
    }

    /**
     * Verifies a webhook of paypal, see the "verify webhook signature" section of the paypal rest docs.
     *
     * The signed message is `transmissionId|transmissionTime|webhookId|crc32(rawBody)` and it is
     * verified with the certificate of the notification itself - paypal rotates those, so there is no
     * certificate of our own to configure.
     *
     * @param array<string, string> $headers
     */
    public function verifyWebhookSignature(string $rawBody, array $headers): bool
    {
        $transmissionId = $headers[self::HEADER_TRANSMISSION_ID] ?? '';
        $transmissionTime = $headers[self::HEADER_TRANSMISSION_TIME] ?? '';
        $signature = $headers[self::HEADER_TRANSMISSION_SIG] ?? '';
        $certificateUrl = $headers[self::HEADER_CERT_URL] ?? '';

        if ($transmissionId === '' || $transmissionTime === '' || $signature === '' || $certificateUrl === '') {
            return false;
        }

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
            $this->webhookId(),
            (string) crc32($rawBody),
        ]);

        try {
            return openssl_verify($message, $decoded, $certificate, $this->authAlgorithm($headers)) === 1;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * The certificate of a notification, fetched once per api object and only from paypal.
     */
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
     * @param array<string, string> $headers
     */
    private function authAlgorithm(array $headers): int
    {
        $algorithm = strtoupper(trim($headers[self::HEADER_AUTH_ALGO] ?? ''));

        return self::AUTH_ALGORITHMS[$algorithm] ?? \OPENSSL_ALGO_SHA256;
    }
}
