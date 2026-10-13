<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Provider\Alipay;

use Alipay\OpenAPISDK\Api\AlipayTradeApi;
use Alipay\OpenAPISDK\Configuration;
use Alipay\OpenAPISDK\Util\AlipayConfigUtil;
use Alipay\OpenAPISDK\Util\GenericExecuteApi;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\AbstractPaymentApi;
use Shopwell\Core\System\Payment\Gateway\Money;
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
