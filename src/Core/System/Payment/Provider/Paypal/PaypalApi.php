<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Provider\Paypal;

use PaypalServerSdkLib\Authentication\ClientCredentialsAuthCredentialsBuilder;
use PaypalServerSdkLib\Environment;
use PaypalServerSdkLib\Exceptions\ApiException;
use PaypalServerSdkLib\Models\CapturedPayment;
use PaypalServerSdkLib\Models\CaptureStatus;
use PaypalServerSdkLib\Models\Order;
use PaypalServerSdkLib\Models\OrderStatus;
use PaypalServerSdkLib\Models\PurchaseUnit;
use PaypalServerSdkLib\PaypalServerSdkClient;
use PaypalServerSdkLib\PaypalServerSdkClientBuilder;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\AbstractPaymentApi;
use Shopwell\Core\System\Payment\Gateway\Money;
use Shopwell\Core\System\Payment\Gateway\QueryResult;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\ResolvedPaymentConfig;
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
    final public const PROVIDER = 'paypal';

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

    /**
     * The capture states of paypal mapped to what the platform does with the order. A (partially)
     * refunded capture was paid before anything else.
     */
    private const QUERY_CAPTURE_STATES = [
        CaptureStatus::COMPLETED => QueryResult::STATUS_PAID,
        CaptureStatus::PARTIALLY_REFUNDED => QueryResult::STATUS_PAID,
        CaptureStatus::REFUNDED => QueryResult::STATUS_PAID,
        CaptureStatus::DECLINED => QueryResult::STATUS_FAILED,
        CaptureStatus::FAILED => QueryResult::STATUS_FAILED,
    ];

    private ?PaypalServerSdkClient $client = null;

    /**
     * @var array<string, \OpenSSLAsymmetricKey|null>
     */
    private array $certificates = [];

    /**
     * @param HttpClientInterface|null $httpClient fetches the certificate a webhook of paypal is signed
     *                                             with, which is the only reason the api is not stateless
     */
    public function __construct(
        ResolvedPaymentConfig $config,
        string $notifyUrl,
        string $returnUrl,
        private readonly ?HttpClientInterface $httpClient = null,
    ) {
        parent::__construct($config, $notifyUrl, $returnUrl);
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
     * Asks paypal what happened to a payment, the capture first and the order behind it second.
     *
     * The capture is what the money of a paypal payment sits on, so it is asked for whenever it is
     * known; the order answers for the payments that were never captured. A resource paypal does not
     * know answers with a 404, which is an answer of its own - `absent` - instead of an error,
     * because that is exactly what the reconciliation asked for.
     *
     * @throws ApiException when paypal rejects the call for another reason
     * @throws PaymentException when paypal answers something the sdk cannot deserialize
     */
    public function query(?string $captureId, ?string $orderId): QueryResult
    {
        if ($captureId !== null && $captureId !== '') {
            return $this->queryCapture($captureId);
        }

        if ($orderId !== null && $orderId !== '') {
            return $this->queryOrder($orderId);
        }

        // without a capture id and without an order id there is nothing paypal could be asked about
        return new QueryResult(QueryResult::STATUS_ABSENT);
    }

    private function queryCapture(string $captureId): QueryResult
    {
        try {
            $response = $this->client()->getPaymentsController()->getCapturedPayment(['captureId' => $captureId]);
        } catch (ApiException $exception) {
            if ($exception->getCode() === 404) {
                return new QueryResult(QueryResult::STATUS_ABSENT, raw: ['error' => $exception->getMessage()]);
            }

            throw $exception;
        }

        $capture = $response->getResult();

        if (!$capture instanceof CapturedPayment) {
            throw PaymentException::gatewayError($this->channel(), 'the interface did not answer a capture');
        }

        return $this->resultOfCapture($capture);
    }

    private function queryOrder(string $orderId): QueryResult
    {
        try {
            $response = $this->client()->getOrdersController()->getOrder(['id' => $orderId]);
        } catch (ApiException $exception) {
            if ($exception->getCode() === 404) {
                return new QueryResult(QueryResult::STATUS_ABSENT, raw: ['error' => $exception->getMessage()]);
            }

            throw $exception;
        }

        $order = $response->getResult();

        if (!$order instanceof Order) {
            throw PaymentException::gatewayError($this->channel(), 'the interface did not answer an order');
        }

        $status = (string) ($order->getStatus() ?? '');

        if ($status === OrderStatus::COMPLETED) {
            return $this->queryCompletedOrder($order);
        }

        return new QueryResult(
            status: $status === OrderStatus::VOIDED ? QueryResult::STATUS_CLOSED : QueryResult::STATUS_PENDING,
            outPaymentNo: $order->getId(),
            channelStatus: $status === '' ? null : $status,
            raw: [
                'id' => $order->getId(),
                'status' => $status,
            ],
        );
    }

    /**
     * A completed order is told by the capture on its purchase units, because a completed order can
     * mean an authorized, a captured or a declined payment - the intent decides nothing on its own.
     */
    private function queryCompletedOrder(Order $order): QueryResult
    {
        foreach ($order->getPurchaseUnits() ?? [] as $unit) {
            if (!$unit instanceof PurchaseUnit) {
                continue;
            }

            foreach ($unit->getPayments()?->getCaptures() ?? [] as $capture) {
                if ($capture instanceof CapturedPayment) {
                    return $this->resultOfCapture($capture);
                }
            }

            // a completed order without a capture booked the money on the purchase unit itself
            $amount = $unit->getAmount();

            return new QueryResult(
                status: QueryResult::STATUS_PAID,
                outPaymentNo: $order->getId(),
                amount: $this->amountOf($amount?->getValue(), $amount?->getCurrencyCode()),
                currency: $amount?->getCurrencyCode(),
                channelStatus: OrderStatus::COMPLETED,
                raw: [
                    'id' => $order->getId(),
                    'status' => OrderStatus::COMPLETED,
                ],
            );
        }

        throw PaymentException::gatewayError($this->channel(), 'the interface answered an order without a purchase unit');
    }

    private function resultOfCapture(CapturedPayment $capture): QueryResult
    {
        $status = (string) ($capture->getStatus() ?? '');
        $amount = $capture->getAmount();

        return new QueryResult(
            status: self::QUERY_CAPTURE_STATES[$status] ?? QueryResult::STATUS_PENDING,
            outPaymentNo: $capture->getId(),
            amount: $this->amountOf($amount?->getValue(), $amount?->getCurrencyCode()),
            currency: $amount?->getCurrencyCode(),
            channelStatus: $status === '' ? null : $status,
            raw: [
                'id' => $capture->getId(),
                'status' => $status,
                'amount' => $amount?->getValue(),
                'currency' => $amount?->getCurrencyCode(),
            ],
        );
    }

    private function amountOf(?string $value, ?string $currency): ?int
    {
        if ($value === null || $currency === null) {
            return null;
        }

        try {
            return Money::toMinor($value, $currency);
        } catch (PaymentException) {
            return null;
        }
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
