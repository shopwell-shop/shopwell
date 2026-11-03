<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Integration\Paypal\Api;

use PaypalServerSdkLib\Authentication\ClientCredentialsAuthCredentialsBuilder;
use PaypalServerSdkLib\Environment;
use PaypalServerSdkLib\Exceptions\ApiException;
use PaypalServerSdkLib\Models\AmountWithBreakdown;
use PaypalServerSdkLib\Models\CapturedPayment;
use PaypalServerSdkLib\Models\CheckoutPaymentIntent;
use PaypalServerSdkLib\Models\Money as PaypalMoney;
use PaypalServerSdkLib\Models\Order;
use PaypalServerSdkLib\Models\OrderApplicationContext;
use PaypalServerSdkLib\Models\OrderRequest;
use PaypalServerSdkLib\Models\OrderStatus;
use PaypalServerSdkLib\Models\PurchaseUnit;
use PaypalServerSdkLib\Models\PurchaseUnitRequest;
use PaypalServerSdkLib\Models\RefundRequest;
use PaypalServerSdkLib\PaypalServerSdkClient;
use PaypalServerSdkLib\PaypalServerSdkClientBuilder;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\AbstractPaymentApi;
use Shopwell\Core\System\Payment\Gateway\Request\Money;
use Shopwell\Core\System\Payment\Gateway\Request\QueryResult;
use Shopwell\Core\System\Payment\Integration\Paypal\Request\PaypalPaymentStatus;
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
     * The codes of a call that produced no judgement about the money: the platform could not talk to
     * paypal at all, or paypal answered something that is not a verdict on the request. A payout
     * action reads them as unknown and never as failed - only a refusal paypal actually issued may
     * book a transfer as failed, anything else would send a second payout after a timeout.
     */
    final public const ERROR_NO_HTTP_CLIENT = 'http_client';
    final public const ERROR_AUTHORIZATION = 'authorization';
    final public const ERROR_TRANSPORT = 'transport';
    final public const ERROR_INVALID_RESPONSE = 'invalid_response';

    /**
     * The header paypal de-duplicates a payout by - it honours it for 30 days, which is the idempotency
     * window of the interface.
     */
    private const HEADER_REQUEST_ID = 'PayPal-Request-Id';

    /**
     * The payouts resource. The installed sdk carries no controller for it, so the two calls below
     * speak to it directly.
     */
    private const PAYOUTS_PATH = '/v1/payments/payouts';

    private ?PaypalServerSdkClient $client = null;

    /**
     * The verifier of the webhooks, kept for the lifetime of the api so the certificate it fetched
     * once is reused by the notifications that follow.
     */
    private ?PaypalWebhookSignature $webhookSignature = null;

    /**
     * @param HttpClientInterface|null $httpClient fetches the certificate a webhook of paypal is signed
     *                                             with and carries the calls of the payouts resource,
     *                                             which the sdk does not cover - the only reason the api
     *                                             is not stateless
     * @param PaypalServerSdkClient|null $client an already built sdk client; without one it is built
     *                                           from the credentials of the payment on first use. A
     *                                           caller that holds a client of its own - one that already
     *                                           carries a token, for instance - hands it over instead
     */
    public function __construct(
        ResolvedPaymentConfig $config,
        string $notifyUrl,
        string $returnUrl,
        private readonly ?HttpClientInterface $httpClient = null,
        ?PaypalServerSdkClient $client = null,
    ) {
        parent::__construct($config, $notifyUrl, $returnUrl);

        $this->client = $client;
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
        // a notification without the signature headers is unverified and not a configuration error:
        // the order is still reconciled by a query, and a missing webhook id must not fail that path
        if (!PaypalWebhookSignature::hasSignatureHeaders($headers)) {
            return false;
        }

        // one verifier per api object: paypal rotates its certificates, and a batch of notifications
        // of the same transmission must not download the certificate again for each of them
        $this->webhookSignature ??= new PaypalWebhookSignature($this->httpClient);

        return $this->webhookSignature->verify($this->webhookId(), $rawBody, $headers);
    }

    /**
     * Opens an order, `POST /v1/checkout/orders`.
     *
     * Paypal has no h5 product: the order carries an `approve` link the payer is redirected to, which
     * is the redirect flow - with `return=representation` the answer carries it right away, so the
     * redirect needs no second call.
     *
     * The payment number of the platform rides as the custom id and the trade number of the app as
     * the invoice id - both are what the webhook of paypal hands back, and both are needed to match a
     * notification to its payment.
     *
     * @param array{amount: string, currency: string, payment_no: string, invoice_id: string, description: string, return_url: string, brand_name: string} $order
     *
     * @return array<string, mixed> the answer of the interface; a rejected call is an answer as well
     *                              and carries `error.code` and `error.message`. This method never
     *                              throws - what an error means is decided by the action.
     */
    public function createOrder(array $order): array
    {
        $purchaseUnit = new PurchaseUnitRequest(new AmountWithBreakdown(
            $order['currency'],
            $order['amount']
        ));
        $purchaseUnit->setCustomId($order['payment_no']);
        $purchaseUnit->setInvoiceId($order['invoice_id']);

        if ($order['description'] !== '') {
            $purchaseUnit->setDescription($order['description']);
        }

        $applicationContext = new OrderApplicationContext();
        // the payer approves the order at paypal, so the wording of the button is paypal's business
        $applicationContext->setUserAction('PAY_NOW');
        $applicationContext->setShippingPreference('NO_SHIPPING');

        if ($order['return_url'] !== '') {
            $applicationContext->setReturnUrl($order['return_url']);
            $applicationContext->setCancelUrl($order['return_url']);
        }

        if ($order['brand_name'] !== '') {
            $applicationContext->setBrandName(mb_substr($order['brand_name'], 0, 127));
        }

        $orderRequest = new OrderRequest(CheckoutPaymentIntent::CAPTURE, [$purchaseUnit]);
        $orderRequest->setApplicationContext($applicationContext);

        try {
            $response = $this->client()->getOrdersController()->createOrder([
                'body' => $orderRequest,
                'prefer' => 'return=representation',
            ]);
        } catch (ApiException $exception) {
            return self::rejectedAnswerOf($exception);
        } catch (\Throwable $exception) {
            return self::errorAnswer(self::ERROR_TRANSPORT, $exception->getMessage(), '');
        }

        return self::decodedAnswer($response->getBody());
    }

    /**
     * Refunds a capture, `POST /v1/payments/captures/{capture_id}/refund`.
     *
     * The refund number of the platform rides as `PayPal-Request-Id` and as the custom id of the
     * refund: the header makes a repeated call answer the refund it created the first time instead of
     * refunding twice, the custom id is what the webhook hands back.
     *
     * @param array{amount: string, currency: string, refund_no: string, reason: string} $refund
     *
     * @return array<string, mixed> the answer of the interface; a rejected call is an answer as well
     *                              and carries `error.code` and `error.message`. This method never
     *                              throws - what an error means is decided by the action.
     */
    public function refundCapture(string $captureId, array $refund, string $requestId): array
    {
        $body = new RefundRequest();
        $body->setAmount(new PaypalMoney($refund['currency'], $refund['amount']));
        $body->setCustomId($refund['refund_no']);

        if ($refund['reason'] !== '') {
            $body->setNoteToPayer(mb_substr($refund['reason'], 0, 255));
        }

        try {
            $response = $this->client()->getPaymentsController()->refundCapturedPayment([
                'captureId' => $captureId,
                'body' => $body,
                'paypalRequestId' => $requestId,
                'prefer' => 'return=representation',
            ]);
        } catch (ApiException $exception) {
            return self::rejectedAnswerOf($exception);
        } catch (\Throwable $exception) {
            return self::errorAnswer(self::ERROR_TRANSPORT, $exception->getMessage(), '');
        }

        return self::decodedAnswer($response->getBody());
    }

    /**
     * The url the payer is sent to to approve the order, null when the answer carries none.
     *
     * @param array<string, mixed> $answer an order of the interface
     */
    public static function approveUrlOf(array $answer): ?string
    {
        $links = $answer['links'] ?? null;

        if (!\is_array($links)) {
            return null;
        }

        foreach ($links as $link) {
            if (!\is_array($link) || ($link['rel'] ?? null) !== 'approve') {
                continue;
            }

            $href = $link['href'] ?? null;

            if (\is_string($href) && $href !== '') {
                return $href;
            }
        }

        return null;
    }

    /**
     * The message of a rejection of the interface, null when the answer is no rejection.
     *
     * @param array<string, mixed> $answer
     */
    public static function rejectionMessageOf(array $answer): ?string
    {
        return self::rejectionFieldOf($answer, 'message');
    }

    /**
     * The code of a rejection of the interface, null when the answer carries none - the transfer
     * actions map their failure to it.
     *
     * @param array<string, mixed> $answer
     */
    public static function rejectionCodeOf(array $answer): ?string
    {
        return self::rejectionFieldOf($answer, 'code');
    }

    /**
     * Submits a payout, `POST /v1/payments/payouts`.
     *
     * Paypal has no single-transfer endpoint: a payout is a batch of items, and a single payout is a
     * batch of one - the business content is built by
     * {@see \Shopwell\Core\System\Payment\Integration\Paypal\Action\PaypalTransferAction}.
     *
     * The installed sdk ships no payouts controller, so the call is made against the base uri of the
     * sdk client with the bearer token of its client credentials - the sdk stays the one place the
     * environment and the authorization of the account are resolved.
     *
     * `PayPal-Request-Id` is honoured by paypal for 30 days; a call repeated with the same id answers
     * the batch it created the first time instead of moving the money twice.
     *
     * @param array<string, mixed> $body the business content, wire snake_case
     *
     * @return array<string, mixed> the answer of the interface; a rejected call is an answer as well
     *                              and carries `error.code` and `error.message`. This method never
     *                              throws - what an error means is decided by the action.
     */
    public function createPayout(array $body, string $requestId): array
    {
        return $this->payoutCall('POST', self::PAYOUTS_PATH, $body, $requestId);
    }

    /**
     * Asks paypal what happened to a payout batch, `GET /v1/payments/payouts/{payout_batch_id}`.
     *
     * The batch is the object the transfer query is made for - its `items` are where the fate of the
     * money actually sits, the batch status alone does not tell it.
     *
     * @return array<string, mixed> the answer of the interface; a rejected call is an answer as well
     *                              and carries `error.code` and `error.message`. This method never
     *                              throws - what an error means is decided by the action.
     */
    public function retrievePayout(string $batchId): array
    {
        return $this->payoutCall('GET', self::PAYOUTS_PATH . '/' . rawurlencode($batchId), null, null);
    }

    private function queryCapture(string $captureId): QueryResult
    {
        try {
            $response = $this->client()->getPaymentsController()->getCapturedPayment(['captureId' => $captureId]);
        } catch (ApiException $exception) {
            if ($exception->getCode() === 404) {
                return QueryResult::absent($exception->getMessage());
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
                return QueryResult::absent($exception->getMessage());
            }

            throw $exception;
        }

        $order = $response->getResult();

        if (!$order instanceof Order) {
            throw PaymentException::gatewayError($this->channel(), 'the interface did not answer an order');
        }

        $status = $order->getStatus() ?? '';

        if ($status === OrderStatus::COMPLETED) {
            return $this->queryCompletedOrder($order);
        }

        return new QueryResult(
            status: PaypalPaymentStatus::order($status),
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
        $status = $capture->getStatus() ?? '';
        $amount = $capture->getAmount();

        return new QueryResult(
            status: PaypalPaymentStatus::capture($status),
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
     * One call of the payouts resource.
     *
     * Nothing of this method throws: a transport failure, a refusal of the interface and an answer
     * that is not json are all reported the way the actions read them, because only the action knows
     * what a failed call means for the transfer it belongs to.
     *
     * @param array<string, mixed>|null $body
     *
     * @return array<string, mixed>
     */
    private function payoutCall(string $method, string $path, ?array $body, ?string $requestId): array
    {
        $httpClient = $this->httpClient;

        if ($httpClient === null) {
            // the factory of the api hands the http client over - without it there is no way to talk
            // to the payouts resource at all, which is no answer about the money
            return self::errorAnswer(self::ERROR_NO_HTTP_CLIENT, 'the api carries no http client', '');
        }

        try {
            // the sdk resolves the credentials and the environment of the account - the url and the
            // token are taken from it, nothing is resolved a second time here
            $baseUri = rtrim($this->client()->getBaseUri(), '/');
            $token = $this->bearerToken();
        } catch (\Throwable $exception) {
            return self::errorAnswer(self::ERROR_AUTHORIZATION, $exception->getMessage(), '');
        }

        $headers = [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        if ($requestId !== null) {
            $headers[self::HEADER_REQUEST_ID] = $requestId;
        }

        $options = ['headers' => $headers];

        if ($body !== null) {
            $options['json'] = $body;
        }

        try {
            $response = $httpClient->request($method, $baseUri . $path, $options);

            $status = $response->getStatusCode();
            $content = $response->getContent(false);
        } catch (\Throwable $exception) {
            return self::errorAnswer(self::ERROR_TRANSPORT, $exception->getMessage(), '');
        }

        $decoded = json_decode($content, true);
        $answer = \is_array($decoded) ? $decoded : null;

        if ($status < 200 || $status >= 300) {
            return self::errorAnswer(
                self::errorFieldOf($answer, 'name', 'error') ?? 'http_' . $status,
                self::errorFieldOf($answer, 'message', 'error_description') ?? 'paypal answered HTTP ' . $status,
                $answer ?? $content,
                // the status is what tells a refusal paypal judged from an outage it never judged
                $status,
            );
        }

        if ($answer === null) {
            return self::errorAnswer(self::ERROR_INVALID_RESPONSE, 'paypal answered no json body', $content);
        }

        return $answer;
    }

    /**
     * The bearer token of the client credentials of the account.
     *
     * The token is fetched through the sdk, so the credentials and the environment are resolved in one
     * place. A token the client was built with and that has not expired yet is used as is - the
     * transfer actions build one api per transfer and there is no reason to ask paypal for a token
     * that is already at hand.
     */
    private function bearerToken(): string
    {
        $auth = $this->client()->getClientCredentialsAuth();
        $token = $auth->getOAuthToken();

        if ($token === null || $auth->isTokenExpired($token)) {
            $token = $auth->fetchToken();
        }

        return $token->getAccessToken();
    }

    /**
     * The answer of a call that produced no usable body, normalized the way the actions read it.
     *
     * @param array<string, mixed>|string $raw
     *
     * @return array<string, mixed>
     */
    private static function errorAnswer(string $code, string $message, array|string $raw, ?int $status = null): array
    {
        return [
            'error' => [
                'code' => $code,
                'message' => $message,
                // only a call that got an http answer carries one - the rest never reached paypal
                'status' => $status,
            ],
            // the original body rides along - the evidence of a support case
            'raw' => $raw,
        ];
    }

    /**
     * The body of a successful answer, decoded; a body that is no json is an answer of its own.
     *
     * @return array<string, mixed>
     */
    private static function decodedAnswer(string $body): array
    {
        $decoded = json_decode($body, true);

        if (!\is_array($decoded)) {
            return self::errorAnswer(self::ERROR_INVALID_RESPONSE, 'paypal answered no json body', $body);
        }

        return $decoded;
    }

    /**
     * The answer of a rejection of the interface, normalized the way the actions read it.
     *
     * The rest api names the code `name` and the message `message`; the oauth style of the same
     * family uses `error` and `error_description`, so both are read.
     *
     * @return array<string, mixed>
     */
    private static function rejectedAnswerOf(ApiException $exception): array
    {
        $rawBody = $exception->getHttpResponse()?->getRawBody() ?? '';
        $decoded = json_decode($rawBody, true);
        $body = \is_array($decoded) ? $decoded : null;

        return self::errorAnswer(
            self::errorFieldOf($body, 'name', 'error') ?? (string) $exception->getCode(),
            self::errorFieldOf($body, 'message', 'error_description') ?? $exception->getMessage(),
            $body ?? $rawBody,
        );
    }

    /**
     * One field of the error of a rejected call; an answer that is no rejection carries none.
     *
     * @param array<string, mixed> $answer
     */
    private static function rejectionFieldOf(array $answer, string $field): ?string
    {
        $error = $answer['error'] ?? null;

        if (!\is_array($error)) {
            return null;
        }

        $value = $error[$field] ?? null;

        return \is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * One string field of an error body of paypal. The rest api names the code `name` and the
     * message `message`; the oauth style of the same family uses `error` and `error_description`, so
     * both are read.
     *
     * @param array<string, mixed>|null $decoded
     */
    private static function errorFieldOf(?array $decoded, string $key, string $fallbackKey): ?string
    {
        if ($decoded === null) {
            return null;
        }

        foreach ([$key, $fallbackKey] as $name) {
            $value = $decoded[$name] ?? null;

            if (\is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }
}
