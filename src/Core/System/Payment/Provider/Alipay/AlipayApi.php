<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Provider\Alipay;

use Alipay\OpenAPISDK\Api\AlipayTradeApi;
use Alipay\OpenAPISDK\ApiException;
use Alipay\OpenAPISDK\Configuration;
use Alipay\OpenAPISDK\Model\AlipayTradeQueryModel;
use Alipay\OpenAPISDK\Model\AlipayTradeQueryResponseModel;
use Alipay\OpenAPISDK\Util\AlipayConfigUtil;
use Alipay\OpenAPISDK\Util\GenericExecuteApi;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\AbstractPaymentApi;
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
     * The channels of alipay as they are declared in `Resources/config/alipay.xml`. The names are the
     * contract between the declaration and the capture action, which is the only place a channel
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

    private ?AlipayConfigUtil $configUtil = null;

    private ?Configuration $configuration = null;

    private ?AlipayTradeApi $tradeApi = null;

    private ?GenericExecuteApi $genericApi = null;

    /**
     * The trade states of alipay mapped to what the platform does with the order. Everything alipay
     * may still settle on its own is pending.
     */
    private const QUERY_STATES = [
        'TRADE_SUCCESS' => QueryResult::STATUS_PAID,
        'TRADE_FINISHED' => QueryResult::STATUS_PAID,
        'TRADE_CLOSED' => QueryResult::STATUS_CLOSED,
    ];

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

        $tradeStatus = (string) ($answer->getTradeStatus() ?? '');
        $totalAmount = $answer->getTotalAmount();

        return new QueryResult(
            status: $tradeStatus === ''
                ? QueryResult::STATUS_ABSENT
                : (self::QUERY_STATES[$tradeStatus] ?? QueryResult::STATUS_PENDING),
            outPaymentNo: $answer->getTradeNo(),
            amount: $this->amountOf($totalAmount, $currency),
            currency: strtoupper($currency),
            channelStatus: $tradeStatus === '' ? null : $tradeStatus,
            raw: [
                'out_trade_no' => $answer->getOutTradeNo(),
                'trade_no' => $answer->getTradeNo(),
                'trade_status' => $tradeStatus,
                'total_amount' => $totalAmount,
            ],
        );
    }

    /**
     * Alipay answers the v3 query with a 404 when it does not know the trade; the error code of the
     * legacy gateway says the same and is kept as the second marker.
     */
    private function isTradeUnknown(ApiException $exception): bool
    {
        return $exception->getCode() === 404 || str_contains((string) $exception->getResponseBody(), 'TRADE_NOT_EXIST');
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

    private static function stripPem(string $key): string
    {
        return trim((string) preg_replace('/-----(BEGIN|END)[A-Z ]*-----[\\r\\n]*/', '', $key));
    }
}
