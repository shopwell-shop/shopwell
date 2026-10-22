<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Provider\Wechat;

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\HandlerStack;
use Psr\Http\Message\ResponseInterface;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\AbstractPaymentApi;
use Shopwell\Core\System\Payment\Gateway\CloseResult;
use Shopwell\Core\System\Payment\Gateway\Payload;
use Shopwell\Core\System\Payment\Gateway\QueryResult;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\ResolvedPaymentConfig;
use WeChatPay\Builder;
use WeChatPay\BuilderChainable;
use WeChatPay\Crypto\Rsa;

/**
 * The wechat pay api of one payment, built on the api v3.
 *
 * The sdk signs every request with the merchant private key and verifies the signature of every answer
 * with the platform certificates, so a payment is only ever accepted when it really comes from wechat.
 * The same platform certificates verify the incoming notifications, the api v3 key decrypts them.
 *
 * Everything the merchant account consists of lives in the credentials - the appid does not: it is
 * bound to the merchant id, but differs between the products of wechat, so it is a parameter of the
 * channel and read through {@see appId()}.
 *
 * @internal
 */
#[Package('framework')]
final class WechatApi extends AbstractPaymentApi
{
    final public const PROVIDER = 'wechat';

    /**
     * The channels of wechat pay as they are declared in {@see WechatProvider::getChannels()}. They share
     * the merchant account and differ in the way the payer is handed over (and in their appid).
     */
    final public const CHANNEL_H5 = 'wechat_h5';

    final public const CHANNEL_NATIVE = 'wechat_native';

    final public const CHANNEL_JSAPI = 'wechat_jsapi';

    final public const CHANNEL_MINI = 'wechat_mini';

    final public const CHANNEL_APP = 'wechat_app';

    /**
     * The name of the channel parameter that carries the appid of a channel. It belongs to the channel
     * and not to the merchant account, because the appid is bound to the merchant id and differs
     * between the products of wechat - the h5 payment and the jsapi payment use different ones.
     */
    final public const PARAM_APP_ID = 'appid';

    /**
     * The runtime parameter of the jsapi and mini program channels: the openid of the payer.
     */
    final public const RUNTIME_OPEN_ID = 'openid';

    final public const MCH_ID = 'mch_id';

    /**
     * The serial number of the merchant certificate.
     */
    final public const MCH_SERIAL = 'mch_serial';

    final public const MCH_PRIVATE_KEY = 'mch_private_key';

    /**
     * The wechat platform certificates as `[serial => certificate]`, needed to verify answers.
     */
    final public const PLATFORM_CERTS = 'platform_certs';

    /**
     * A single platform certificate, the counterpart of `platform_serial`.
     */
    final public const PLATFORM_CERT = 'platform_cert';

    final public const PLATFORM_SERIAL = 'platform_serial';

    /**
     * The api v3 key, needed to decrypt the notifications.
     */
    final public const API_V3_KEY = 'apiv3_key';

    /**
     * The code {@see queryTransfer()} answers when wechat does not know the bill - the counterpart of
     * the 404 {@see query()} maps to `absent`, read by the transfer query as "nothing was opened".
     */
    final public const TRANSFER_BILL_NOT_FOUND = 'NOT_FOUND';

    /**
     * The order query of the api v3, answered for the merchant order number.
     */
    private const URI_QUERY = 'v3/pay/transactions/out-trade-no/%s';

    /**
     * The close of the api v3, answered for the merchant order number.
     */
    private const URI_CLOSE = 'v3/pay/transactions/out-trade-no/%s/close';

    /**
     * The merchant transfer of the api v3 - the payout of the platform to one openid, the product of
     * 2025-01-15. The batch transfer (`v3/transfer/batches`) and the v2 promotion transfer are the
     * predecessors, both are not what a new merchant opens.
     */
    private const URI_TRANSFER = 'v3/fund-app/mch-transfer/transfer-bills';

    /**
     * The query of a merchant transfer bill, answered for the out bill number of the merchant for the
     * last 30 days only.
     */
    private const URI_TRANSFER_QUERY = 'v3/fund-app/mch-transfer/transfer-bills/out-bill-no/%s';

    /**
     * The trade states of wechat mapped to what the platform does with the order. A refunded trade
     * was paid before anything else, everything wechat may still settle on its own is pending.
     */
    private const QUERY_STATES = [
        'SUCCESS' => QueryResult::STATUS_PAID,
        'REFUND' => QueryResult::STATUS_PAID,
        'CLOSED' => QueryResult::STATUS_CLOSED,
        'PAYERROR' => QueryResult::STATUS_FAILED,
    ];

    private ?BuilderChainable $builder = null;

    /**
     * @param HandlerStack|null $handler the guzzle handler the sdk client is built on; production
     *                                   leaves it null and the sdk creates its own, the tests hand in
     *                                   one that answers from canned responses and records what left
     */
    public function __construct(
        ResolvedPaymentConfig $config,
        string $notifyUrl,
        string $returnUrl,
        private readonly ?HandlerStack $handler = null,
    ) {
        parent::__construct($config, $notifyUrl, $returnUrl);
    }

    /**
     * The appid of the channel the payment was opened with - see {@see self::PARAM_APP_ID}.
     */
    public function appId(): string
    {
        return $this->requireParam(self::PARAM_APP_ID);
    }

    public function builder(): BuilderChainable
    {
        if ($this->builder instanceof BuilderChainable) {
            return $this->builder;
        }

        $config = [
            'mchid' => $this->requireCredential(self::MCH_ID),
            'serial' => $this->requireCredential(self::MCH_SERIAL),
            'privateKey' => $this->privateKey(),
            'certs' => $this->platformCerts(),
        ];

        if ($this->handler instanceof HandlerStack) {
            $config['handler'] = $this->handler;
        }

        return $this->builder = Builder::factory($config);
    }

    /**
     * Calls a fixed api v3 endpoint and returns the decoded answer.
     *
     * The sdk signs the request with the merchant private key and verifies the signature of the
     * answer, so the body returned here is authenticated already - the headers are the ones the
     * request needs next to a signature (a `Wechatpay-Serial`, for instance).
     *
     * **The path goes through `chain()`, so it may not carry a business number or an upper case
     * letter.** `Builder::normalize()` rewrites every upper case character of a chain segment to `-`
     * plus its lower case, which is meant for camel case endpoint names. A path with our own numbers
     * in it (`PG20261018AB12CD34`) would be rewritten character by character and reach a path that
     * exists nowhere - use {@see send()} for those.
     *
     * @param array<string, mixed> $payload
     * @param array<string, string> $headers
     *
     * @throws \Throwable when the channel rejects the call
     *
     * @return array<string, mixed>
     */
    public function post(string $uri, array $payload, array $headers = []): array
    {
        $options = ['json' => $payload];

        if ($headers !== []) {
            $options['headers'] = $headers;
        }

        $response = $this->builder()->chain($uri)->post($options);

        return self::decode($response);
    }

    /**
     * Submits a merchant transfer, the payout of the platform to one openid.
     *
     * The body is built by the action - what the scene of the transfer consists of is a decision of
     * the business case, not of the api. The headers carry the `Wechatpay-Serial` when the request
     * encrypts a `user_name`, because the header names the certificate whose public key did it.
     *
     * @param array<string, mixed> $body
     * @param array<string, string> $headers
     *
     * @throws \Throwable when wechat rejects the call
     *
     * @return array<string, mixed>
     */
    public function createTransfer(array $body, array $headers = []): array
    {
        return $this->post(self::URI_TRANSFER, $body, $headers);
    }

    /**
     * Asks wechat what happened to a merchant transfer bill.
     *
     * A bill wechat does not know answers with a 404 - only the last 30 days are known. That is an
     * answer of its own and no failure: the query action reads the `error.code` as "the bill does not
     * exist" instead of having to tell a missing bill from a broken channel. Everything else bubbles.
     *
     * @throws \Throwable when wechat rejects the call for another reason
     *
     * @return array<string, mixed>
     */
    public function queryTransfer(string $outBillNo): array
    {
        try {
            // the out bill number is `[0-9a-zA-Z]` - see send() for why this can not be chained
            return self::decode($this->send('GET', \sprintf(self::URI_TRANSFER_QUERY, $outBillNo)));
        } catch (ClientException $exception) {
            if ($exception->getResponse()->getStatusCode() === 404) {
                return ['error' => [
                    'code' => self::TRANSFER_BILL_NOT_FOUND,
                    'status' => 404,
                    'message' => $exception->getMessage(),
                ]];
            }

            throw $exception;
        }
    }

    /**
     * Asks wechat what happened to a trade, the order query of the api v3.
     *
     * A trade wechat does not know answers with a 404, which is an answer of its own - `absent` -
     * instead of an error, because that is exactly what the reconciliation asked for.
     *
     * @throws \Throwable when wechat rejects the call for another reason
     */
    public function query(string $outTradeNo): QueryResult
    {
        try {
            // our payment numbers start with `PG` and continue in upper case hex - see send()
            $answer = self::decode($this->send('GET', \sprintf(self::URI_QUERY, $outTradeNo), [
                'query' => ['mchid' => $this->requireCredential(self::MCH_ID)],
            ]));
        } catch (ClientException $exception) {
            $response = $exception->getResponse();

            if ($response->getStatusCode() === 404) {
                return new QueryResult(QueryResult::STATUS_ABSENT, raw: ['error' => $exception->getMessage()]);
            }

            throw $exception;
        }

        $state = Payload::string($answer, 'trade_state') ?? '';
        $amount = Payload::map($answer, 'amount');

        return new QueryResult(
            status: self::QUERY_STATES[$state] ?? QueryResult::STATUS_PENDING,
            outPaymentNo: Payload::string($answer, 'transaction_id'),
            amount: Payload::int($amount, 'total'),
            currency: strtoupper(Payload::string($amount, 'currency', 'CNY') ?? 'CNY'),
            channelStatus: $state === '' ? null : $state,
            raw: $answer,
        );
    }

    /**
     * Closes a trade, the close of the api v3.
     *
     * Wechat answers 204 without a body when the trade is closed. A trade it does not know, a trade
     * that is already paid and a trade that is already closed are errors of the api, but they are
     * answers of the caller's interest instead of failures - see {@see CloseResult}. Only everything
     * else is a real failure and bubbles up.
     *
     * @throws \Throwable when wechat rejects the call for another reason
     */
    public function close(string $outTradeNo): CloseResult
    {
        try {
            // our payment numbers start with `PG` and continue in upper case hex - see send()
            $response = $this->send('POST', \sprintf(self::URI_CLOSE, $outTradeNo), [
                'json' => ['mchid' => $this->requireCredential(self::MCH_ID)],
            ]);
        } catch (ClientException $exception) {
            return $this->closeAnswerOf($exception);
        }

        return new CloseResult(CloseResult::STATUS_CLOSED, channelStatus: (string) $response->getStatusCode());
    }

    /**
     * The platform certificate of a serial number, used to verify a notification.
     */
    public function platformCertOf(?string $serial): ?string
    {
        $certs = $this->platformCerts();

        if ($serial === null || !isset($certs[$serial])) {
            // a channel may rotate its certificates, so an unknown serial is not necessarily an attack
            return \count($certs) === 1 ? (string) reset($certs) : null;
        }

        return (string) $certs[$serial];
    }

    /**
     * The platform certificate a transfer encrypts its `user_name` with, as `[serial, certificate]`.
     *
     * `Wechatpay-Serial` names the very certificate whose public key encrypted the field, so the
     * serial is part of the answer and can not be looked up from the certificate alone. A channel
     * that holds several platform certificates (a rotation is under way) and does not name the
     * current one in `platform_serial` has no certificate this method could pick: guessing would send
     * a serial wechat does not accept for the ciphertext, which burns the out number of the transfer.
     *
     * @throws PaymentException when no certificate is configured or the one to use is ambiguous
     *
     * @return array{serial: string, certificate: string}
     */
    public function platformCertificate(): array
    {
        $certs = $this->platformCerts();
        $serial = $this->credential(self::PLATFORM_SERIAL);

        if ($serial !== null && isset($certs[$serial])) {
            return ['serial' => $serial, 'certificate' => $certs[$serial]];
        }

        if (\count($certs) === 1) {
            return ['serial' => (string) array_key_first($certs), 'certificate' => (string) reset($certs)];
        }

        throw PaymentException::invalidProviderDeclaration(\sprintf(
            'The wechat channel declares %d platform certificates but names no current one - set the credential "%s" to the serial whose certificate encrypts the user name.',
            \count($certs),
            self::PLATFORM_SERIAL,
        ));
    }

    public function publicKeyOf(string $certificate): \OpenSSLAsymmetricKey
    {
        $key = Rsa::from($certificate, Rsa::KEY_TYPE_PUBLIC);

        if (!$key instanceof \OpenSSLAsymmetricKey) {
            throw PaymentException::invalidProviderDeclaration('The wechat platform certificate does not contain a usable public key.');
        }

        return $key;
    }

    public function apiV3Key(): string
    {
        return $this->requireCredential(self::API_V3_KEY);
    }

    /**
     * Signs a message with the merchant private key, which is what the sdk of the app verifies before
     * it hands the payment over to wechat: the jsapi and the app channel answer parameters the app
     * signs nothing about, they are signed here.
     */
    public function sign(string $message): string
    {
        return Rsa::sign($message, $this->merchantPrivateKey());
    }

    public function merchantPrivateKey(): \OpenSSLAsymmetricKey
    {
        $key = Rsa::from($this->privateKey(), Rsa::KEY_TYPE_PRIVATE);

        if (!$key instanceof \OpenSSLAsymmetricKey) {
            throw PaymentException::invalidProviderDeclaration('The wechat merchant private key does not contain a usable private key.');
        }

        return $key;
    }

    /**
     * The close answer a rejection of wechat decides on its own.
     *
     * Pure on purpose and covered one code per row: mistaking "paid" for "closed" would close an
     * order the money belongs to, which is the most expensive mistake this link can make.
     *
     * @param array<string, mixed> $raw
     *
     * @return CloseResult|null null when the code is no close answer at all
     */
    public static function closeResultForError(string $code, array $raw = []): ?CloseResult
    {
        return match ($code) {
            'ORDER_NOT_EXIST' => new CloseResult(CloseResult::STATUS_NOT_FOUND, channelStatus: $code, raw: $raw),
            'ORDER_PAID' => new CloseResult(CloseResult::STATUS_ALREADY_PAID, channelStatus: $code, raw: $raw),
            // a trade that is already closed is closed, which is exactly what the caller wanted
            'ORDER_CLOSED' => new CloseResult(CloseResult::STATUS_CLOSED, channelStatus: $code, raw: $raw),
            default => null,
        };
    }

    /**
     * Sends one api v3 request on a path the caller built and returns the raw answer.
     *
     * **Every path that carries one of our own numbers goes through here and never through
     * `chain()`.** The sdk normalizes a chain segment with `Builder::normalize()`
     * (`vendor/wechatpay/wechatpay/src/Builder.php:91-98`): every upper case letter becomes `-` plus
     * its lower case, because the segments are camel case endpoint names. A segment that carries a
     * business number is rewritten character by character by that rule - our payment numbers start
     * with `PG` and continue in upper case hex (`PaymentOrderService::generatePaymentNo()`), so
     * `PG20261018AB12CD34` arrives at wechat as `-p-g20261018-a-b12-c-d34`, a path that exists
     * nowhere. Wechat answers 404 for it, which is indistinguishable from "this trade does not
     * exist" - the worst possible answer, because the reconciliation books it.
     *
     * The client behind the driver is the signed one: `Builder::factory()` puts the signer and the
     * verifier on the very guzzle client `getDriver()` hands out, so a request sent here carries the
     * `Authorization` of the merchant certificate exactly like a chained one.
     *
     * @param array<string, mixed> $options
     *
     * @throws \Throwable when wechat rejects the call
     */
    private function send(string $method, string $path, array $options = []): ResponseInterface
    {
        return $this->builder()->getDriver()->request($method, $path, $options);
    }

    /**
     * The decoded body of an answer, as `array<string, mixed>`.
     *
     * @return array<string, mixed>
     */
    private static function decode(ResponseInterface $response): array
    {
        $decoded = json_decode((string) $response->getBody(), true);

        return \is_array($decoded) ? $decoded : [];
    }

    /**
     * @throws ClientException
     */
    private function closeAnswerOf(ClientException $exception): CloseResult
    {
        $decoded = json_decode((string) $exception->getResponse()->getBody(), true);

        /** @var array<string, mixed> $decoded */
        $decoded = \is_array($decoded) ? $decoded : [];
        $code = Payload::string($decoded, 'code') ?? '';

        $result = self::closeResultForError($code, $decoded);

        if (!$result instanceof CloseResult) {
            throw $exception;
        }

        return $result;
    }

    /**
     * @return array<string, string>
     */
    private function platformCerts(): array
    {
        return WechatPlatformCertificates::of($this->credentials());
    }

    /**
     * The private key of the merchant certificate as pem, the sdk signs the requests with it.
     */
    private function privateKey(): string
    {
        $key = $this->requireCredential(self::MCH_PRIVATE_KEY);

        if (str_contains($key, '-----BEGIN')) {
            return $key;
        }

        // the key of the merchant certificate is a pkcs#8 key, only the envelope is missing
        return "-----BEGIN PRIVATE KEY-----\n" . wordwrap($key, 64, "\n", true) . "\n-----END PRIVATE KEY-----";
    }
}
