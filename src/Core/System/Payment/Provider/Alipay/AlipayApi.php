<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Provider\Alipay;

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
use Alipay\OpenAPISDK\Model\AlipayTradeQueryModel;
use Alipay\OpenAPISDK\Model\AlipayTradeQueryResponseModel;
use Alipay\OpenAPISDK\Model\BankcardExtInfo;
use Alipay\OpenAPISDK\Model\Participant;
use Alipay\OpenAPISDK\Model\TransferSceneReportInfo;
use Alipay\OpenAPISDK\Util\AlipayConfigUtil;
use Alipay\OpenAPISDK\Util\GenericExecuteApi;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\AbstractPaymentApi;
use Shopwell\Core\System\Payment\Gateway\CloseResult;
use Shopwell\Core\System\Payment\Gateway\Money;
use Shopwell\Core\System\Payment\Gateway\QueryResult;
use Shopwell\Core\System\Payment\PaymentException;

/**
 * The alipay api of one payment.
 *
 * The sdk objects are built lazily, because they need the credentials of the app the payment belongs
 * to: the private key signs the request, the alipay public key verifies the notification.
 *
 * The h5 payment is the classic gateway call `alipay.trade.wap.pay`, which is why the generic api of
 * the sdk is used for it; refunds go through the rest api.
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
     * The alipay public key without its pem envelope, the way the sdk expects it.
     */
    public function publicKey(): string
    {
        return self::stripPem($this->requireCredential(self::PUBLIC_KEY));
    }

    public function configUtil(): AlipayConfigUtil
    {
        if ($this->configUtil instanceof AlipayConfigUtil) {
            return $this->configUtil;
        }

        $configUtil = new AlipayConfigUtil();
        $configUtil->setAppId($this->requireCredential(self::APP_ID));
        $configUtil->setPrivateKey(self::wrapPem($this->requireCredential(self::PRIVATE_KEY)));
        $configUtil->setAlipayPublicKey($this->publicKey());
        $configUtil->setSignType($this->signType());
        $configUtil->setServerUrl($this->gatewayUrl());
        $configUtil->setFormat('json');
        $configUtil->setCharset('UTF-8');

        return $this->configUtil = $configUtil;
    }

    /**
     * The gateway calls of the sdk use the server url of the config, the rest calls use this host.
     */
    public function configuration(): Configuration
    {
        if ($this->configuration instanceof Configuration) {
            return $this->configuration;
        }

        $configuration = new Configuration();
        $configuration->setHost($this->gatewayUrl());

        return $this->configuration = $configuration;
    }

    public function tradeApi(): AlipayTradeApi
    {
        return $this->tradeApi ??= new AlipayTradeApi(null, $this->configuration(), null, 0, $this->configUtil());
    }

    public function genericApi(): GenericExecuteApi
    {
        return $this->genericApi ??= new GenericExecuteApi($this->configUtil(), null, $this->configuration());
    }

    public function fundTransApi(): AlipayFundTransUniApi
    {
        return $this->fundTransApi ??= new AlipayFundTransUniApi(null, $this->configuration(), null, 0, $this->configUtil());
    }

    public function fundTransCommonApi(): AlipayFundTransCommonApi
    {
        return $this->fundTransCommonApi ??= new AlipayFundTransCommonApi(null, $this->configuration(), null, 0, $this->configUtil());
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
    public function transfer(array $content): array
    {
        $model = new AlipayFundTransUniTransferModel();

        foreach ($content as $key => $value) {
            if (\is_scalar($value)) {
                $setter = 'set' . str_replace(' ', '', ucwords(str_replace('_', ' ', (string) $key)));

                if (method_exists($model, $setter)) {
                    // the business content is written in the snake_case of the wire format, so the
                    // name of the setter has to be derived from it - unknown keys are skipped above
                    // @phpstan-ignore method.dynamicName
                    $model->{$setter}((string) $value);
                }
            }
        }

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
    public function transferQuery(string $outBizNo, string $productCode, string $bizScene): array
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
     * The bare key the sdk expects. Public because a notification is verified outside the api: the
     * verifier of a payout notification holds the credential as it was configured, not as the api
     * normalized it.
     */
    public static function stripPem(string $key): string
    {
        return trim((string) preg_replace('/-----(BEGIN|END)[A-Z ]*-----[\\r\\n]*/', '', $key));
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

    private static function wrapPem(string $key): string
    {
        if (str_contains($key, '-----BEGIN')) {
            return $key;
        }

        return "-----BEGIN RSA PRIVATE KEY-----\n" . wordwrap($key, 64, "\n", true) . "\n-----END RSA PRIVATE KEY-----";
    }
}
