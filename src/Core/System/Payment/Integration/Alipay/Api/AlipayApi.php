<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Integration\Alipay\Api;

use Alipay\OpenAPISDK\Api\AlipayFundTransCommonApi;
use Alipay\OpenAPISDK\Api\AlipayFundTransUniApi;
use Alipay\OpenAPISDK\Api\AlipayTradeApi;
use Alipay\OpenAPISDK\ApiException;
use Alipay\OpenAPISDK\Configuration;
use Alipay\OpenAPISDK\Model\AlipayFundTransCommonQueryResponseModel;
use Alipay\OpenAPISDK\Model\AlipayFundTransUniTransferModel;
use Alipay\OpenAPISDK\Model\AlipayFundTransUniTransferResponseModel;
use Alipay\OpenAPISDK\Model\AlipayTradeCloseModel;
use Alipay\OpenAPISDK\Model\AlipayTradeCloseResponseModel;
use Alipay\OpenAPISDK\Model\AlipayTradeCreateModel;
use Alipay\OpenAPISDK\Model\AlipayTradeCreateResponseModel;
use Alipay\OpenAPISDK\Model\AlipayTradePrecreateModel;
use Alipay\OpenAPISDK\Model\AlipayTradePrecreateResponseModel;
use Alipay\OpenAPISDK\Model\AlipayTradeQueryModel;
use Alipay\OpenAPISDK\Model\AlipayTradeQueryResponseModel;
use Alipay\OpenAPISDK\Model\AlipayTradeRefundModel;
use Alipay\OpenAPISDK\Model\AlipayTradeRefundResponseModel;
use Alipay\OpenAPISDK\Model\BankcardExtInfo;
use Alipay\OpenAPISDK\Model\Participant;
use Alipay\OpenAPISDK\Model\TransferSceneReportInfo;
use Alipay\OpenAPISDK\Util\AlipayConfigUtil;
use Alipay\OpenAPISDK\Util\AlipaySignature;
use Alipay\OpenAPISDK\Util\GenericExecuteApi;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\AbstractPaymentApi;
use Shopwell\Core\System\Payment\Gateway\Request\CloseResult;
use Shopwell\Core\System\Payment\Gateway\Request\Money;
use Shopwell\Core\System\Payment\Gateway\Request\QueryResult;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\ResolvedPaymentConfig;

/**
 * The alipay api of one payment.
 *
 * The sdk objects are built lazily, because they need the credentials of the app the payment belongs
 * to: the private key signs the request, the alipay public key verifies the notification.
 *
 * The h5 payment is the classic gateway call `alipay.trade.wap.pay`, which is why the generic api of
 * the sdk is used for it; refunds go through the rest api.
 *
 * Every call of the interface is made from here, and the methods are of two kinds:
 *
 *  - an answer that is a fact about the payment - {@see self::query()}, {@see self::close()} - is
 *    narrowed to a {@see QueryResult} or a {@see CloseResult} on the spot, because every channel means
 *    the same thing by it and no caller should read it twice;
 *  - an answer only the caller can read - the capture and payout products, whose shape differs per
 *    channel - is handed back in the shape of the wire format. A rejection is an empty answer there
 *    and not an exception: whether a payment that could not be opened is a failure or a call to try
 *    again is the decision of the action, not of the merchant account.
 *
 * The business content is always passed in the snake_case of the wire format, the way the action
 * records it, so the record of a call and the call itself can not tell different stories.
 *
 * @internal
 */
#[Package('framework')]
final class AlipayApi extends AbstractPaymentApi
{
    final public const PROVIDER = 'alipay';

    /**
     * The channels of alipay as they are declared in {@see AlipayProvider::getChannels()}. The names are
     * the contract between the declaration and the capture action, which is the only place a channel
     * matters - adding a channel means declaring it there and teaching the action to open it.
     */
    final public const CHANNEL_H5 = 'alipay_h5';

    final public const CHANNEL_WEB = 'alipay_web';

    final public const CHANNEL_QR = 'alipay_qr';

    final public const CHANNEL_APP = 'alipay_app';

    final public const CHANNEL_MINI = 'alipay_mini';

    final public const APP_ID = 'app_id';
    final public const PRIVATE_KEY = 'private_key';
    final public const PUBLIC_KEY = 'alipay_public_key';
    final public const SIGN_TYPE = 'sign_type';
    final public const GATEWAY_URL = 'gateway_url';
    final public const SANDBOX = 'sandbox';

    final public const DEFAULT_GATEWAY_URL = 'https://openapi.alipay.com';
    final public const SANDBOX_GATEWAY_URL = 'https://openapi-sandbox.dl.alipaydev.com';
    final public const DEFAULT_SIGN_TYPE = 'RSA2';

    /**
     * The two interfaces of a payout. They are the v1 names of the methods - the sdk calls their
     * successors on the json gateway of v3, the wire content and the answers stay the same shapes.
     */
    final public const METHOD_UNI_TRANSFER = 'alipay.fund.trans.uni.transfer';

    final public const METHOD_COMMON_QUERY = 'alipay.fund.trans.common.query';

    /**
     * The code of an accepted call. It only says the interface took the request - the fate of the
     * money is the `status` next to it, `SUCCESS` is as little a terminal answer as `DEALING`.
     */
    final public const TRANSFER_CALL_OK = '10000';

    /**
     * The trade states of alipay mapped to what the platform does with the order. Everything alipay
     * may still settle on its own is pending.
     */
    private const QUERY_STATES = [
        'TRADE_SUCCESS' => QueryResult::STATUS_PAID,
        'TRADE_FINISHED' => QueryResult::STATUS_PAID,
        'TRADE_CLOSED' => QueryResult::STATUS_CLOSED,
    ];

    private ?AlipayConfigUtil $configUtil = null;

    private ?Configuration $configuration = null;

    private ?AlipayTradeApi $tradeApi = null;

    private ?GenericExecuteApi $genericApi = null;

    private ?AlipayFundTransUniApi $fundTransApi = null;

    private ?AlipayFundTransCommonApi $fundTransCommonApi = null;

    /**
     * @param AlipayTradeApi|null $tradeApi an already built sdk object of the trade api; without one it
     *                                      is built from the credentials of the payment on first use. A
     *                                      caller that holds one of its own hands it over - the tests
     *                                      hand in one that records what left and answers from canned
     *                                      answers
     */
    public function __construct(
        ResolvedPaymentConfig $config,
        string $notifyUrl,
        string $returnUrl,
        ?AlipayTradeApi $tradeApi = null,
    ) {
        parent::__construct($config, $notifyUrl, $returnUrl);

        $this->tradeApi = $tradeApi;
    }

    /**
     * The gateway of the app: the sandbox gateway when the sandbox of the interface is switched on,
     * otherwise the configured gateway or the production one.
     */
    public function gatewayUrl(): string
    {
        if ($this->isSandbox()) {
            return self::SANDBOX_GATEWAY_URL;
        }

        return rtrim($this->credential(self::GATEWAY_URL, self::DEFAULT_GATEWAY_URL) ?? self::DEFAULT_GATEWAY_URL, '/');
    }

    public function isSandbox(): bool
    {
        $sandbox = $this->credential(self::SANDBOX);

        return $sandbox !== null && $sandbox !== '' && $sandbox !== '0';
    }

    public function signType(): string
    {
        return strtoupper($this->credential(self::SIGN_TYPE, self::DEFAULT_SIGN_TYPE) ?? self::DEFAULT_SIGN_TYPE);
    }

    /**
     * The alipay public key without its pem envelope, the way the classic signature util expects it
     * - `rsaCheckV1` wraps the envelope by itself. The response verification of the v3 calls takes
     * the enveloped shape, which `configUtil()` builds.
     */
    public function publicKey(): string
    {
        return self::stripPem($this->requireCredential(self::PUBLIC_KEY));
    }

    /**
     * The sdk configuration of the merchant account: the appid, the two keys, the sign type and the
     * gateway every call of the interface is made against.
     */
    public function configUtil(): AlipayConfigUtil
    {
        if ($this->configUtil instanceof AlipayConfigUtil) {
            return $this->configUtil;
        }

        $configUtil = new AlipayConfigUtil();
        $configUtil->setAppId($this->requireCredential(self::APP_ID));
        $configUtil->setPrivateKey(self::wrapPem($this->requireCredential(self::PRIVATE_KEY)));
        $configUtil->setAlipayPublicKey(self::wrapPublicKeyPem($this->publicKey()));
        $configUtil->setSignType($this->signType());
        $configUtil->setServerUrl($this->gatewayUrl());
        $configUtil->setFormat('json');
        $configUtil->setCharset('UTF-8');

        return $this->configUtil = $configUtil;
    }

    /**
     * Alipay expects the amount as a decimal string, the platform stores it in the minor unit.
     *
     * @throws PaymentException
     */
    public function amount(int $minor, string $currency): string
    {
        return Money::toDecimal($minor, $currency);
    }

    /**
     * The page of a gateway product: what the app hands the payer to.
     *
     * The two web products of alipay - `alipay.trade.wap.pay` and `alipay.trade.page.pay` - answer a
     * url for a `GET` and a self submitting form for a `POST`; the form is what a desktop browser
     * needs when the url would grow too long. Which of the two it is, is a decision of the channel and
     * is passed in.
     *
     * The parameters are the public parameters of the call, `biz_content` among them: the sdk turns
     * them into the signed query of the wire format.
     *
     * @param array<string, mixed> $parameters
     *
     * @throws \Throwable when the sdk cannot sign the call
     */
    public function paymentPage(string $method, string $httpMethod, array $parameters): string
    {
        return (string) $this->genericApi()->pageExecute($method, $httpMethod, $parameters);
    }

    /**
     * The signed order string of an app payment, `alipay.trade.app.pay` of the interface.
     *
     * Alipay answers nothing here: the string is signed on this side and handed to the sdk of the app,
     * which passes it on to alipay. The business content has to be part of the parameters - the sdk
     * sends what it is given as public parameters, and the trade belongs into the one field the
     * interface reads it from.
     *
     * @param array<string, mixed> $parameters
     *
     * @throws \Throwable when the sdk cannot sign the call
     */
    public function paymentOrderString(string $method, array $parameters): string
    {
        return (string) $this->genericApi()->sdkExecute($method, $parameters);
    }

    /**
     * Opens a trade the payer scans, `alipay.trade.precreate` of the rest api.
     *
     * The trade is created on this call, the payer pays it by scanning the code the answer carries -
     * which is why the notification of a qr payment arrives the very same way as the one of the
     * redirect products: once the trade exists, the channel does not matter anymore.
     *
     * @param array<string, mixed> $content the business content, wire snake_case
     *
     * @throws ApiException when the call fails without an answer of the interface
     *
     * @return array<string, mixed> the `qr_code` of the trade, empty when alipay rejected the call
     */
    public function precreatePayment(array $content): array
    {
        $trade = new AlipayTradePrecreateModel();
        self::fill($trade, $content);
        $trade->setNotifyUrl($this->notifyUrl);

        $answer = $this->tradeApi()->precreate($trade);

        return $answer instanceof AlipayTradePrecreateResponseModel
            ? ['qr_code' => $answer->getQrCode()]
            : [];
    }

    /**
     * Opens the trade a mini program pays, `alipay.trade.create` of the rest api.
     *
     * Unlike the other channels this one creates the trade before the payer sees it: the mini program
     * hands the trade number to `my.tradePay`, and alipay notifies the platform like it does for a
     * scanned code once the payer has paid it.
     *
     * @param array<string, mixed> $content the business content, wire snake_case
     *
     * @throws ApiException when the call fails without an answer of the interface
     *
     * @return array<string, mixed> the `trade_no` of the trade, empty when alipay rejected the call
     */
    public function createPayment(array $content): array
    {
        $trade = new AlipayTradeCreateModel();
        self::fill($trade, $content);
        $trade->setNotifyUrl($this->notifyUrl);

        $answer = $this->tradeApi()->create($trade);

        return $answer instanceof AlipayTradeCreateResponseModel
            ? ['trade_no' => $answer->getTradeNo()]
            : [];
    }

    /**
     * Refunds a trade, `alipay.trade.refund` of the rest api.
     *
     * Alipay settles a refund synchronously, so a trade number in the answer is a refund that is
     * through. The `out_request_no` of the content makes the call idempotent: a retry with the same
     * number refunds nothing a second time.
     *
     * @param array<string, mixed> $content the business content, wire snake_case
     *
     * @throws ApiException when the call fails without an answer of the interface
     *
     * @return array<string, mixed> the `trade_no` and `fund_change` of the answer, empty when alipay
     *                              rejected the call
     */
    public function refundPayment(array $content): array
    {
        $refund = new AlipayTradeRefundModel();
        self::fill($refund, $content);

        $answer = $this->tradeApi()->refund($refund);

        return $answer instanceof AlipayTradeRefundResponseModel
            ? ['trade_no' => $answer->getTradeNo(), 'fund_change' => $answer->getFundChange()]
            : [];
    }

    /**
     * Submits a payout, `alipay.fund.trans.uni.transfer` of the interface.
     *
     * The business content comes in the wire snake_case - the action builds it and records it, this
     * method only turns it into the sdk models and back. The answer is normalized the same way: the
     * code of the call plus, on an accepted call, the status of the transfer with its references. A
     * rejected call is an answer of its own as well instead of an error, because `SYSTEM_ERROR` and
     * `PAYMENT_FAIL` are exactly the answers the retry logic waits for - only something that is no
     * answer at all (a network failure, a body that carries no codes) bubbles up.
     *
     * @param array<string, mixed> $content the business content, wire snake_case
     *
     * @throws ApiException when the call fails without an answer of the interface
     * @throws PaymentException when the interface answers something else than a transfer
     *
     * @return array<string, mixed> the answer of the interface
     */
    public function createTransfer(array $content): array
    {
        $model = new AlipayFundTransUniTransferModel();
        self::fill($model, $content);

        $payee = $content['payee_info'] ?? null;

        if (\is_array($payee)) {
            $model->setPayeeInfo(self::payeeOf($payee));
        }

        $reportInfos = $content['transfer_scene_report_infos'] ?? null;

        if (\is_array($reportInfos)) {
            $model->setTransferSceneReportInfos(self::reportInfosOf($reportInfos));
        }

        try {
            $answer = $this->fundTransApi()->transfer($model);
        } catch (ApiException $exception) {
            return $this->fundAnswerOf($exception);
        }

        if (!$answer instanceof AlipayFundTransUniTransferResponseModel) {
            // alipay answers its default response when it rejects the call
            throw PaymentException::gatewayError($this->channel(), 'the interface did not answer a transfer');
        }

        return [
            'code' => self::TRANSFER_CALL_OK,
            'out_biz_no' => $answer->getOutBizNo(),
            'order_id' => $answer->getOrderId(),
            'pay_fund_order_id' => $answer->getPayFundOrderId(),
            'status' => $answer->getStatus(),
            'trans_date' => $answer->getTransDate(),
        ];
    }

    /**
     * Asks alipay what happened to a payout, `alipay.fund.trans.common.query` of the interface - the
     * query of the transfers of the new and the legacy interface alike, which `order.query` is not.
     *
     * Queried by the out business number, alipay demands the very `product_code` and `biz_scene` the
     * submission was built with - the caller knows them from the identity of the recipient. Alipay
     * recommends taking the answer of a query made 3 to 5 minutes after the submission as the truth
     * about the transfer.
     *
     * @param string $outBizNo the out business number of the transfer
     * @param string $productCode the product code the submission was built with
     * @param string $bizScene the scene the submission was built with
     *
     * @throws ApiException when the call fails without an answer of the interface
     * @throws PaymentException when the interface answers something else than a transfer
     *
     * @return array<string, mixed> the answer of the interface
     */
    public function queryTransfer(string $outBizNo, string $productCode, string $bizScene): array
    {
        try {
            $answer = $this->fundTransCommonApi()->query($productCode, $bizScene, $outBizNo);
        } catch (ApiException $exception) {
            return $this->fundAnswerOf($exception);
        }

        if (!$answer instanceof AlipayFundTransCommonQueryResponseModel) {
            throw PaymentException::gatewayError($this->channel(), 'the interface did not answer a transfer');
        }

        return [
            'code' => self::TRANSFER_CALL_OK,
            'out_biz_no' => $answer->getOutBizNo(),
            'order_id' => $answer->getOrderId(),
            'pay_fund_order_id' => $answer->getPayFundOrderId(),
            'status' => $answer->getStatus(),
            'error_code' => $answer->getErrorCode(),
            'fail_reason' => $answer->getFailReason(),
        ];
    }

    /**
     * Asks alipay what happened to a trade, `alipay.trade.query` of the rest api.
     *
     * A trade alipay does not know is an answer of its own - `absent` - instead of an error, because
     * that is exactly what the reconciliation asked for; everything else alipay rejects the call for
     * is a real failure and bubbles up.
     *
     * @throws ApiException when alipay rejects the call for another reason
     * @throws PaymentException when alipay answers something the sdk cannot deserialize
     */
    public function query(string $outTradeNo, string $currency): QueryResult
    {
        $query = new AlipayTradeQueryModel();
        // the payment number of the platform is the merchant order number alipay knows
        $query->setOutTradeNo($outTradeNo);

        try {
            $answer = $this->tradeApi()->query($query);
        } catch (ApiException $exception) {
            if ($this->isTradeUnknown($exception)) {
                return new QueryResult(QueryResult::STATUS_ABSENT, raw: ['error' => $exception->getMessage()]);
            }

            throw $exception;
        }

        if (!$answer instanceof AlipayTradeQueryResponseModel) {
            throw PaymentException::gatewayError($this->channel(), 'the interface did not answer a trade');
        }

        $tradeStatus = $answer->getTradeStatus();
        $totalAmount = $answer->getTotalAmount();

        return new QueryResult(
            status: $tradeStatus === null
                ? QueryResult::STATUS_ABSENT
                : (self::QUERY_STATES[$tradeStatus] ?? QueryResult::STATUS_PENDING),
            outPaymentNo: $answer->getTradeNo(),
            amount: $this->amountOf($totalAmount, $currency),
            currency: strtoupper($currency),
            channelStatus: $tradeStatus,
            raw: [
                'out_trade_no' => $answer->getOutTradeNo(),
                'trade_no' => $answer->getTradeNo(),
                'trade_status' => $tradeStatus,
                'total_amount' => $totalAmount,
            ],
        );
    }

    /**
     * Closes a trade, `alipay.trade.close` of the rest api.
     *
     * The answers the caller needs are answers of their own instead of errors: a trade alipay does not
     * know, a trade that is already closed, and a trade that cannot be closed because it was paid.
     * `TRADE_STATUS_ERROR` covers the last two alike, so the trade itself is asked about in that case -
     * a close that is taken for a failure while the payer really paid would end an order the money
     * belongs to, and a close that is taken for a payment while the trade is only closed would book a
     * payment that never happened.
     *
     * @throws ApiException when alipay rejects the call for another reason
     */
    public function close(string $outTradeNo, string $currency): CloseResult
    {
        $close = new AlipayTradeCloseModel();
        $close->setOutTradeNo($outTradeNo);

        try {
            $answer = $this->tradeApi()->close($close);
        } catch (ApiException $exception) {
            return $this->closeAnswerOf($exception, $outTradeNo, $currency);
        }

        if (!$answer instanceof AlipayTradeCloseResponseModel) {
            throw PaymentException::gatewayError($this->channel(), 'the interface did not answer the close call');
        }

        return new CloseResult(CloseResult::STATUS_CLOSED, raw: ['out_trade_no' => $answer->getOutTradeNo()]);
    }

    /**
     * The form encoded body of a notification, as the parameters that were signed.
     *
     * Every notification of this interface arrives form encoded - the payment notification posts
     * `out_trade_no`, the ant message of a payout `out_biz_no` - and `parse_str` answers a string for
     * every value, which is the shape both verifiers expect. A notification is read before it is
     * verified, so this is a pure parsing step.
     *
     * @return array<string, string>
     */
    public static function parametersOf(string $rawBody): array
    {
        $parsed = [];
        parse_str($rawBody, $parsed);

        $parameters = [];
        foreach ($parsed as $key => $value) {
            if (\is_string($value)) {
                $parameters[(string) $key] = $value;
            }
        }

        return $parameters;
    }

    /**
     * Whether a notification was signed by alipay for this merchant account.
     *
     * A body without a `sign` is not verified, and it is no configuration error either: the signature
     * of the notification of this interface is checked against the alipay public key, which is the
     * one the merchant account published to alipay.
     *
     * @param array<string, string> $parameters
     *
     * @throws PaymentException when the merchant account carries no public key
     */
    public function verifyNotificationSignature(array $parameters): bool
    {
        if (!isset($parameters['sign'])) {
            return false;
        }

        // the sign type of the notification itself wins, otherwise alipay would have signed with
        // another algorithm than the one configured for the app
        $signType = strtoupper($parameters[self::SIGN_TYPE] ?? '');

        return self::verifySignature(
            $parameters,
            $this->publicKey(),
            $signType === '' ? $this->signType() : $signType,
        );
    }

    /**
     * Verifies a form encoded notification against an alipay public key in the bare base64 shape.
     *
     * `rsaCheckV1` wraps the pem envelope by itself, so a key that carries one has to be stripped
     * first - the two shapes are not interchangeable, which is the one thing about this signature
     * that is easy to get wrong. A verification that blows up is a verification that failed.
     *
     * @param array<string, string> $parameters
     * @param string $publicKey the alipay public key without its pem envelope
     */
    public static function verifySignature(array $parameters, string $publicKey, string $signType): bool
    {
        try {
            return AlipaySignature::rsaCheckV1($parameters, $publicKey, $signType);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * The close answer a rejection of alipay decides on its own.
     *
     * A trade alipay does not know cannot be paid anymore, whatever the caller did - that one is
     * decided here. `ACQ.TRADE_STATUS_ERROR` is *not*: it covers "already paid" and "already closed"
     * alike, so the trade itself has to be asked, which is not a decision but a request.
     *
     * Pure on purpose and covered one code per row: mistaking "paid" for "closed" would close an
     * order the money belongs to, which is the most expensive mistake this link can make.
     *
     * @param array<string, mixed> $raw
     *
     * @return CloseResult|null null when the code alone does not decide the answer
     */
    public static function closeResultForError(string $code, array $raw = []): ?CloseResult
    {
        return match ($code) {
            'ACQ.TRADE_NOT_EXIST', 'TRADE_NOT_EXIST' => new CloseResult(
                CloseResult::STATUS_NOT_FOUND,
                channelStatus: $code,
                raw: $raw
            ),
            default => null,
        };
    }

    /**
     * What a trade that could not be closed means for the order: a paid trade must never be closed
     * locally, a closed one already is what the caller wanted. Everything else is no answer to a
     * close and stays a failure.
     *
     * Pure as well - the query that produced the status is the only part that talks to alipay.
     *
     * @return CloseResult|null null when the trade is neither paid nor closed
     */
    public static function closeResultForTrade(string $tradeStatus): ?CloseResult
    {
        return match ($tradeStatus) {
            QueryResult::STATUS_PAID => new CloseResult(CloseResult::STATUS_ALREADY_PAID),
            QueryResult::STATUS_CLOSED => new CloseResult(CloseResult::STATUS_CLOSED),
            default => null,
        };
    }

    /**
     * The bare key the sdk expects. Public because a notification is verified outside the api as
     * well: the verifier of a payout notification holds the credential as it was configured, not as
     * the api normalized it.
     */
    public static function stripPem(string $key): string
    {
        return trim((string) preg_replace('/-----(BEGIN|END)[A-Z ]*-----[\\r\\n]*/', '', $key));
    }

    /**
     * Alipay answers the v3 query with a 404 when it does not know the trade; the error code of the
     * legacy gateway says the same and is kept as the second marker.
     */
    private function isTradeUnknown(ApiException $exception): bool
    {
        if ($exception->getCode() === 404) {
            return true;
        }

        $body = $exception->getResponseBody();

        return \is_string($body) && str_contains($body, 'TRADE_NOT_EXIST');
    }

    /**
     * The key as the pem envelope the response verification of the sdk expects: the v3 config util
     * hands the alipay public key straight to `openssl_verify()`, and openssl only coerces a pem
     * string - a bare base64 body fails with "cannot be coerced into a public key". The classic
     * signature util wraps by itself, which is what `stripPem` is for, so the two shapes both exist
     * and every consumer takes the one its verifier needs.
     */
    private static function wrapPublicKeyPem(string $key): string
    {
        if (str_contains($key, '-----BEGIN')) {
            return $key;
        }

        return "-----BEGIN PUBLIC KEY-----\n" . wordwrap($key, 64, "\n", true) . "\n-----END PUBLIC KEY-----";
    }

    /**
     * The rejection of the interface as the answer it is: the codes of the body speak for themselves,
     * the transfer decides what they mean - see the actions.
     *
     * A body without a parsable code is no answer and keeps being an error.
     *
     * @return array<string, mixed>
     */
    private function fundAnswerOf(ApiException $exception): array
    {
        $body = $exception->getResponseBody();
        $decoded = match (true) {
            \is_string($body) => json_decode($body, true),
            $body instanceof \stdClass => json_decode((string) json_encode($body), true),
            default => null,
        };

        if (!\is_array($decoded)) {
            throw $exception;
        }

        $code = $decoded['code'] ?? null;
        $code = \is_string($code) ? $code : '';

        if ($code === '') {
            throw $exception;
        }

        $subCode = $decoded['sub_code'] ?? null;
        $subMsg = $decoded['sub_msg'] ?? $decoded['message'] ?? $decoded['msg'] ?? null;

        return [
            'code' => $code,
            'sub_code' => \is_string($subCode) ? $subCode : '',
            'sub_msg' => \is_string($subMsg) ? $subMsg : '',
            // the original body rides along - when the interface behaves differently than the codes
            // promise, the evidence is the raw message, not the four keys it was narrowed to
            'raw' => $decoded,
        ];
    }

    /**
     * @param array<string, mixed> $payee the `payee_info` of the wire format
     */
    // the sdk models implement ArrayAccess without naming their generic types
    // @phpstan-ignore missingType.generics
    private static function payeeOf(array $payee): Participant
    {
        $participant = new Participant();

        $identityType = $payee['identity_type'] ?? null;

        if (\is_string($identityType) && $identityType !== '') {
            $participant->setIdentityType($identityType);
        }

        $identity = $payee['identity'] ?? null;

        if (\is_string($identity) && $identity !== '') {
            $participant->setIdentity($identity);
        }

        $name = $payee['name'] ?? null;

        if (\is_string($name) && $name !== '') {
            $participant->setName($name);
        }

        $ext = $payee['bankcard_ext_info'] ?? null;

        if (\is_array($ext)) {
            $bankcard = new BankcardExtInfo();
            $bankcard->setAccountType(isset($ext['account_type']) ? (string) $ext['account_type'] : null);
            $bankcard->setInstName(isset($ext['inst_name']) ? (string) $ext['inst_name'] : null);
            $bankcard->setInstProvince(isset($ext['inst_province']) ? (string) $ext['inst_province'] : null);
            $bankcard->setInstCity(isset($ext['inst_city']) ? (string) $ext['inst_city'] : null);
            $bankcard->setInstBranchName(isset($ext['inst_branch_name']) ? (string) $ext['inst_branch_name'] : null);
            $bankcard->setBankCode(isset($ext['bank_code']) ? (string) $ext['bank_code'] : null);
            $participant->setBankcardExtInfo($bankcard);
        }

        return $participant;
    }

    /**
     * @param array<mixed> $reportInfos the `transfer_scene_report_infos` of the wire format
     *
     * @return list<TransferSceneReportInfo>
     */
    // the sdk models implement ArrayAccess without naming their generic types
    // @phpstan-ignore missingType.generics
    private static function reportInfosOf(array $reportInfos): array
    {
        $models = [];

        foreach ($reportInfos as $info) {
            if (!\is_array($info)) {
                continue;
            }

            $model = new TransferSceneReportInfo();
            $model->setInfoType(isset($info['info_type']) ? (string) $info['info_type'] : null);
            $model->setInfoContent(isset($info['info_content']) ? (string) $info['info_content'] : null);
            $models[] = $model;
        }

        return $models;
    }

    /**
     * Copies the business content of the wire format into a model of the sdk.
     *
     * The content is written in the snake_case of the wire format and the models name their setters
     * after the camel case of it, so the name of a setter is derived from the key. A key the model has
     * no setter for is a field this product does not know - it is skipped instead of failing a call
     * the interface would have answered.
     *
     * Nested content (`payee_info`, the report infos) is built by the caller: a setter of the models
     * takes a model of its own, not an array.
     *
     * @param array<string, mixed> $content
     */
    private static function fill(object $model, array $content): void
    {
        foreach ($content as $key => $value) {
            if (!\is_scalar($value)) {
                continue;
            }

            $setter = 'set' . str_replace(' ', '', ucwords(str_replace('_', ' ', (string) $key)));

            if (method_exists($model, $setter)) {
                // @phpstan-ignore method.dynamicName
                $model->{$setter}((string) $value);
            }
        }
    }

    /**
     * @throws ApiException
     */
    private function closeAnswerOf(ApiException $exception, string $outTradeNo, string $currency): CloseResult
    {
        $code = $this->errorCodeOf($exception);
        $raw = ['code' => $code, 'message' => $exception->getMessage()];

        $decided = self::closeResultForError($code, $raw);

        if ($decided instanceof CloseResult) {
            return $decided;
        }

        // the legacy gateway and a body without a parsable code still name the unknown trade
        if ($this->isTradeUnknown($exception)) {
            return new CloseResult(CloseResult::STATUS_NOT_FOUND, raw: $raw);
        }

        $body = $exception->getResponseBody();
        $body = \is_string($body) ? $body : '';

        if (!str_contains($body, 'TRADE_STATUS_ERROR')) {
            throw $exception;
        }

        // "already closed" and "already paid" share this error code, only the trade says which one it is
        $query = $this->query($outTradeNo, $currency);
        $answer = self::closeResultForTrade($query->status);

        if ($answer instanceof CloseResult) {
            return new CloseResult($answer->status, channelStatus: $query->channelStatus, raw: $query->raw);
        }

        // the close was refused and the trade is neither paid nor closed - a real failure
        throw $exception;
    }

    /**
     * The `sub_code` of the rejection, e.g. `ACQ.TRADE_NOT_EXIST`, or an empty string when the body
     * carries none.
     */
    private function errorCodeOf(ApiException $exception): string
    {
        $body = $exception->getResponseBody();

        $decoded = match (true) {
            \is_string($body) => json_decode($body, true),
            $body instanceof \stdClass => json_decode((string) json_encode($body), true),
            default => null,
        };

        if (!\is_array($decoded)) {
            return '';
        }

        foreach (['sub_code', 'code'] as $key) {
            $value = $decoded[$key] ?? null;

            if (\is_string($value) && $value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function amountOf(?string $totalAmount, string $currency): ?int
    {
        if ($totalAmount === null || $totalAmount === '') {
            return null;
        }

        try {
            return Money::toMinor($totalAmount, $currency);
        } catch (PaymentException) {
            return null;
        }
    }

    /**
     * The gateway calls of the sdk use the server url of the config, the rest calls use this host.
     */
    private function configuration(): Configuration
    {
        if ($this->configuration instanceof Configuration) {
            return $this->configuration;
        }

        $configuration = new Configuration();
        $configuration->setHost($this->gatewayUrl());

        return $this->configuration = $configuration;
    }

    private function tradeApi(): AlipayTradeApi
    {
        return $this->tradeApi ??= new AlipayTradeApi(null, $this->configuration(), null, 0, $this->configUtil());
    }

    private function genericApi(): GenericExecuteApi
    {
        return $this->genericApi ??= new GenericExecuteApi($this->configUtil(), null, $this->configuration());
    }

    private function fundTransApi(): AlipayFundTransUniApi
    {
        return $this->fundTransApi ??= new AlipayFundTransUniApi(null, $this->configuration(), null, 0, $this->configUtil());
    }

    private function fundTransCommonApi(): AlipayFundTransCommonApi
    {
        return $this->fundTransCommonApi ??= new AlipayFundTransCommonApi(null, $this->configuration(), null, 0, $this->configUtil());
    }

    private static function wrapPem(string $key): string
    {
        if (str_contains($key, '-----BEGIN')) {
            return $key;
        }

        return "-----BEGIN RSA PRIVATE KEY-----\n" . wordwrap($key, 64, "\n", true) . "\n-----END RSA PRIVATE KEY-----";
    }
}
